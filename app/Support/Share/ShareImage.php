<?php

namespace App\Support\Share;

use App\Models\Ad;
use App\Support\Phone\SomaliPhone;
use App\Support\Theme\SiteColors;
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
 * The picture shown when an ad is shared on WhatsApp, Facebook or Telegram (PR 110, switch "share.image").
 * 1200 × 630: the cover photo on the left; title, price, city and the iibsom name on the right.
 * Never the phone number. Made on the server with GD or Imagick (works on shared hosting), kept on the
 * private disk, and made again when the ad changes (the address carries a version, so apps fetch the new one).
 */
class ShareImage
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    public const FOLDER = 'share';

    private const FONT = 'fonts/Inter-Bold.ttf';

    /** Changes when anything on the picture changes. */
    public static function version(Ad $ad): string
    {
        $cover = $ad->photos->first();

        return substr(sha1(implode('|', [
            $ad->title, $ad->priceLabel(), $ad->city->label, $cover?->id, $cover?->updated_at?->getTimestamp(),
            $ad->category_id, SiteColors::current(), app()->getLocale(), 3, // bump the last number when the design changes
        ])), 0, 12);
    }

    public static function path(Ad $ad, string $version): string
    {
        return self::FOLDER.'/'.$ad->id.'-'.$version.'.jpg';
    }

    /** The finished JPEG (made now if needed), or null when it cannot be made. */
    public static function get(Ad $ad, string $version): ?string
    {
        $disk = Storage::disk('local');
        $path = self::path($ad, $version);
        if ($disk->exists($path)) {
            return $disk->get($path);
        }

        try {
            $jpeg = self::make($ad);
        } catch (Throwable $e) {
            Log::warning('share_image.failed', ['ad_id' => $ad->id, 'error' => class_basename($e)]);

            return null;
        }

        // Older versions of this ad's picture are no longer used.
        foreach ($disk->files(self::FOLDER) as $old) {
            if (str_starts_with(basename($old), $ad->id.'-')) {
                $disk->delete($old);
            }
        }
        $disk->put($path, $jpeg);

        return $jpeg;
    }

    /** Nightly: remove pictures of ads that are gone or not live, and very old files. */
    public static function prune(): int
    {
        $disk = Storage::disk('local');
        $removed = 0;
        $files = collect($disk->files(self::FOLDER));
        $ids = $files->map(fn ($f) => (int) Str::before(basename($f), '-'))->unique()->filter();
        $live = Ad::query()->live()->whereIn('id', $ids)->pluck('id')->flip();
        foreach ($files as $file) {
            $id = (int) Str::before(basename($file), '-');
            if (! $live->has($id) || $disk->lastModified($file) < now()->subDays(60)->getTimestamp()) {
                $disk->delete($file);
                $removed++;
            }
        }

        return $removed;
    }

    private static function make(Ad $ad): string
    {
        $manager = new ImageManager(extension_loaded('imagick') ? new ImagickDriver : new GdDriver);
        $brand = SiteColors::current();
        $canvas = $manager->create(self::WIDTH, self::HEIGHT)->fill('#ffffff');

        // Left: the cover photo, cut to a square (or a calm brand box when there is no photo).
        $photo = self::coverPhoto($manager, $ad);
        if ($photo) {
            $canvas->place($photo->cover(self::HEIGHT, self::HEIGHT), 'top-left');
        } else {
            // No photo: the category name on a brand box (the iibsom name is already on the right).
            $canvas->drawRectangle(0, 0, fn ($r) => $r->size(self::HEIGHT, self::HEIGHT)->background($brand));
            $category = self::lines(self::categoryName($ad), 64, self::HEIGHT - 96, 2);
            foreach ($category as $i => $line) {
                self::write($canvas, $line, self::HEIGHT / 2, self::HEIGHT / 2 + ($i - (count($category) - 1) / 2) * 80, 64, '#ffffff', 'center', 'middle');
            }
        }

        // Right: name, title (up to 3 lines), price, city.
        $x = self::HEIGHT + 48;
        $texts = self::texts($ad);
        self::write($canvas, $texts['name'], $x, 64, 40, $brand);
        $y = 150;
        foreach ($texts['title'] as $line) {
            self::write($canvas, $line, $x, $y, 38, '#1c1917');
            $y += 52;
        }
        self::write($canvas, $texts['price'], $x, $y + 36, 56, $brand);
        self::write($canvas, $texts['city'], $x, $y + 126, 30, '#57534e');
        self::write($canvas, $texts['site'], $x, self::HEIGHT - 72, 24, '#78716c');
        $canvas->drawRectangle(0, self::HEIGHT - 12, fn ($r) => $r->size(self::WIDTH, 12)->background($brand));

        return (string) $canvas->toJpeg(quality: 82);
    }

    /**
     * Every word written on the picture. Only these: never the phone number or the seller's name.
     *
     * @return array{name: string, title: list<string>, price: string, city: string, site: string}
     */
    public static function texts(Ad $ad): array
    {
        return [
            'name' => 'iibsom',
            'title' => self::lines(self::withoutPhoneNumbers($ad->title), 38, self::WIDTH - self::HEIGHT - 96, 3),
            'price' => (string) ($ad->priceLabel() ?: __('ads.page.negotiable')),
            'city' => (string) $ad->city->label,
            'site' => (string) parse_url((string) config('app.url'), PHP_URL_HOST),
        ];
    }

    private static function categoryName(Ad $ad): string
    {
        return translated($ad->category->name);
    }

    /** Phone numbers people type into titles ("call 0615…") never go on the picture. */
    public static function withoutPhoneNumbers(string $text): string
    {
        $clean = preg_replace_callback('/\+?\d[\d \-.()]{7,18}\d/u', fn ($m) => SomaliPhone::normalize($m[0]) !== null ? '' : $m[0], $text) ?? $text;

        return trim((string) preg_replace('/\s+/u', ' ', $clean), " \t,;:-");
    }

    private static function coverPhoto(ImageManager $manager, Ad $ad): ?ImageInterface
    {
        $cover = $ad->photos->first();
        $path = $cover ? ($cover->variants['medium']['webp'] ?? $cover->variants['large']['webp'] ?? null) : null;
        if ($cover === null || $path === null) {
            return null;
        }
        $bytes = Storage::disk($cover->disk)->get($path);

        return $bytes ? $manager->read($bytes) : null;
    }

    private static function write(ImageInterface $image, string $text, int|float $x, int|float $y, int $size, string $color, string $align = 'left', string $valign = 'top'): void
    {
        $image->text($text, (int) $x, (int) $y, function (FontFactory $font) use ($size, $color, $align, $valign) {
            $font->filename(resource_path(self::FONT));
            $font->size($size);
            $font->color($color);
            $font->align($align);
            $font->valign($valign);
        });
    }

    /**
     * Break the title into at most $max lines that fit the width; "…" at the end when it does not all fit.
     *
     * @return list<string>
     */
    public static function lines(string $text, int $size, int $width, int $max): array
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        $line = '';
        while ($words !== [] && count($lines) < $max) {
            $try = $line === '' ? $words[0] : $line.' '.$words[0];
            if (self::textWidth($try, $size) <= $width) {
                $line = $try;
                array_shift($words);
            } elseif ($line === '') {
                // One word wider than the line (a long model number): cut it.
                $lines[] = self::shorten((string) array_shift($words), $size, $width, '');
            } else {
                $lines[] = $line;
                $line = '';
            }
        }
        if ($line !== '') {
            if (count($lines) < $max) {
                $lines[] = $line;
            } else {
                array_unshift($words, $line); // did not fit: counts as "more text"
            }
        }
        if ($words !== [] && $lines !== []) {
            $lines[] = self::shorten((string) array_pop($lines), $size, $width, '…');
        }

        return $lines;
    }

    private static function shorten(string $text, int $size, int $width, string $end): string
    {
        $text = rtrim($text);
        while ($text !== '' && self::textWidth($text.$end, $size) > $width) {
            $text = rtrim(mb_substr($text, 0, -1));
        }

        return $text.$end;
    }

    private static function textWidth(string $text, int $size): int
    {
        $box = imagettfbbox($size * 0.75, 0, resource_path(self::FONT), $text); // GD points ≈ 0.75 × pixels

        return $box === false ? PHP_INT_MAX : abs($box[2] - $box[0]);
    }
}
