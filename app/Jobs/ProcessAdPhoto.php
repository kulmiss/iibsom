<?php

namespace App\Jobs;

use App\Models\AdPhoto;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Typography\FontFactory;
use Throwable;

/**
 * Turns an uploaded photo into safe, small, fast images (background job):
 * - turned the right way up (phone photos are often sideways)
 * - re-encoded, so all hidden data (GPS location, camera info) and any
 *   hidden code is removed
 * - made in 3 sizes, as WebP (+ AVIF when the server supports it)
 * - saved to the photos disk (R2 on the live site), never the app server
 */
class ProcessAdPhoto implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $photoId) {}

    public function handle(): void
    {
        $photo = AdPhoto::find($this->photoId);
        if (! $photo || ! $photo->upload_path || $photo->isReady()) {
            return;
        }

        $manager = new ImageManager(extension_loaded('imagick') ? new ImagickDriver : new GdDriver);
        $source = Storage::disk('local')->get($photo->upload_path);
        $disk = config('iibsom.photos_disk');
        $folder = 'ads/'.now()->format('Y/m').'/'.Str::uuid();
        $canAvif = extension_loaded('imagick') ? in_array('AVIF', \Imagick::queryFormats('AVIF'), true) : function_exists('imageavif');

        $variants = [];
        $width = $height = null;
        $mark = feature('photos.watermark'); // decided once, so all sizes of one photo match

        foreach (config('iibsom.photo_sizes') as $size => $max) {
            $image = $manager->read($source)->orient()->scaleDown($max, $max);
            $width ??= $image->width();
            $height ??= $image->height();
            if ($size === 'large') {
                [$width, $height] = [$image->width(), $image->height()];
            }
            if ($mark && $size !== 'thumb') {
                self::watermark($image);
            }

            $path = "{$folder}/{$size}.webp";
            Storage::disk($disk)->put($path, (string) $image->toWebp(quality: 78), ['visibility' => 'public', 'CacheControl' => 'public, max-age=31536000, immutable']);
            $variants[$size]['webp'] = $path;

            if ($canAvif) {
                $path = "{$folder}/{$size}.avif";
                Storage::disk($disk)->put($path, (string) $image->toAvif(quality: 55), ['visibility' => 'public', 'CacheControl' => 'public, max-age=31536000, immutable']);
                $variants[$size]['avif'] = $path;
            }
        }

        Storage::disk('local')->delete($photo->upload_path);

        $photo->forceFill([
            'disk' => $disk,
            'variants' => $variants,
            'width' => $width,
            'height' => $height,
            'upload_path' => null,
            'status' => 'ready',
        ])->save();
    }

    /**
     * A small, see-through "iibsom" in the bottom corner (switch "photos.watermark").
     * Only the copies we show are marked; there is no other copy, because the uploaded file (with its
     * hidden data like GPS) is deleted after processing.
     */
    public static function watermark(ImageInterface $image): void
    {
        $size = max(14, (int) round(min($image->width(), $image->height()) * 0.05));
        $margin = (int) round($size * 0.6);
        $draw = function (string $colour, int $offset) use ($image, $size, $margin) {
            $image->text('iibsom', $image->width() - $margin + $offset, $image->height() - $margin + $offset, function (FontFactory $font) use ($size, $colour) {
                $font->filename(resource_path('fonts/Inter-Bold.ttf'));
                $font->size($size);
                $font->color($colour);
                $font->align('right');
                $font->valign('bottom');
            });
        };
        $draw('rgba(0, 0, 0, 0.35)', max(1, (int) round($size / 14))); // soft shadow: readable on light photos
        $draw('rgba(255, 255, 255, 0.75)', 0);
    }

    public function failed(Throwable $e): void
    {
        Log::warning('photo.processing_failed', ['photo_id' => $this->photoId, 'error' => $e->getMessage()]);
        AdPhoto::whereKey($this->photoId)->update(['status' => 'failed']);
    }
}
