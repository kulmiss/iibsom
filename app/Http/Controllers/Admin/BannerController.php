<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Support\Admin\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

class BannerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Banner::orderBy('sort')->orderBy('id')->get()->map(fn (Banner $b) => $this->row($b)));
    }

    public function store(Request $request): JsonResponse
    {
        $banner = new Banner;
        $this->save($request, $banner, true);
        Activity::log('banner.created', 'Added banner “'.translated($banner->title).'”', $banner);

        return response()->json($this->row($banner), 201);
    }

    /** POST (multipart) so a new image can be sent. */
    public function update(Request $request, Banner $banner): JsonResponse
    {
        $this->save($request, $banner, false);
        Activity::log('banner.updated', 'Changed banner “'.translated($banner->title).'”', $banner);

        return response()->json($this->row($banner));
    }

    public function destroy(Banner $banner): JsonResponse
    {
        Storage::disk($banner->disk ?? config('iibsom.photos_disk'))->delete(array_values($banner->image ?? []));
        $banner->delete();
        Activity::log('banner.deleted', 'Deleted banner “'.translated($banner->title).'”', $banner);

        return response()->json(['deleted' => true]);
    }

    private function save(Request $request, Banner $banner, bool $isNew): void
    {
        $data = $request->validate([
            'title_en' => ['required', 'string', 'max:100'],
            'title_so' => ['nullable', 'string', 'max:100'],
            // Only links to our own site or https pages (no javascript: etc.).
            'link_url' => ['nullable', 'string', 'max:300', 'regex:#^(/[^\s]*|https://[^\s]+)$#'],
            'enabled' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'image' => [$isNew ? 'required' : 'nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192', 'dimensions:min_width=600,min_height=150'],
        ]);

        $banner->title = array_filter(['en' => strip_tags($data['title_en']), 'so' => isset($data['title_so']) ? strip_tags($data['title_so']) : null]);
        $banner->link_url = $data['link_url'] ?? null;
        $banner->enabled = $request->boolean('enabled', true);
        $banner->sort = (int) ($data['sort'] ?? $banner->sort ?? 0);
        $banner->starts_at = $data['starts_at'] ?? null;
        $banner->ends_at = $data['ends_at'] ?? null;

        if ($request->hasFile('image')) {
            // Re-encode (removes hidden data), 2 sizes, WebP, on the photos disk (R2 live).
            $manager = new ImageManager(extension_loaded('imagick') ? new ImagickDriver : new GdDriver);
            $disk = config('iibsom.photos_disk');
            $folder = 'banners/'.Str::uuid();
            $old = $banner->image ? [$banner->disk, array_values($banner->image)] : null;
            $image = [];
            foreach (['wide' => 1600, 'small' => 800] as $size => $width) {
                $img = $manager->read($request->file('image')->getRealPath())->orient()->scaleDown($width);
                Storage::disk($disk)->put("{$folder}/{$size}.webp", (string) $img->toWebp(quality: 80), ['visibility' => 'public']);
                $image[$size] = "{$folder}/{$size}.webp";
            }
            $banner->disk = $disk;
            $banner->image = $image;
            if ($old) {
                Storage::disk($old[0])->delete($old[1]);
            }
        }

        $banner->save();
    }

    private function row(Banner $b): array
    {
        return [
            'id' => $b->id, 'title_en' => $b->title['en'] ?? '', 'title_so' => $b->title['so'] ?? null, 'link_url' => $b->link_url,
            'enabled' => $b->enabled, 'sort' => $b->sort, 'image' => $b->imageUrl('small'),
            'starts_at' => $b->starts_at?->toDateString(), 'ends_at' => $b->ends_at?->toDateString(),
            'live' => Banner::active()->whereKey($b->id)->exists(),
        ];
    }
}
