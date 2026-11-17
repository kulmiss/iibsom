<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Category;
use App\Models\City;
use App\Support\Admin\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

class BannerController extends Controller
{
    public function index(): JsonResponse
    {
        $since = now('Africa/Mogadishu')->subDays(30)->toDateString();
        $recent = DB::table('banner_clicks')->where('day', '>', $since)->groupBy('banner_id')->selectRaw('banner_id, SUM(clicks) AS c')->pluck('c', 'banner_id');

        return response()->json(Banner::with(['category:id,name', 'city:id,name'])->orderBy('placement')->orderBy('sort')->orderBy('id')->get()
            ->map(fn (Banner $b) => $this->row($b) + ['clicks_30' => (int) ($recent[$b->id] ?? 0)]));
    }

    /** Choices for the form: categories (main → sub) and cities. */
    public function options(): JsonResponse
    {
        return response()->json([
            'banner_ads' => feature('banner_ads'),
            'categories' => Category::with('parent:id,name')->orderBy('parent_id')->orderBy('sort')->get(['id', 'name', 'parent_id'])
                ->map(fn (Category $c) => ['id' => $c->id, 'name' => ($c->parent ? $c->parent->label.' › ' : '').$c->label])->values(),
            'cities' => City::orderBy('sort')->get(['id', 'name'])->map(fn (City $c) => ['id' => $c->id, 'name' => $c->label])->values(),
        ]);
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
            'link_url' => [Rule::requiredIf(fn () => ($request->input('placement') ?? $banner->placement ?? 'slider') !== 'slider'), 'nullable', 'string', 'max:300', 'regex:#^(/[^\s]*|https://[^\s]+)$#'],
            'enabled' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'image' => [$isNew ? 'required' : 'nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192', 'dimensions:min_width=600,min_height=150'],
            // Paid slots need the "Banner ads" switch; the home slider is always allowed.
            'placement' => ['nullable', Rule::in(feature('banner_ads') ? Banner::PLACEMENTS : ['slider'])],
            'advertiser' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
        ], ['placement.in' => 'Turn on the "Banner ads" switch to use paid places.', 'link_url.required' => 'A paid banner needs a link.']);

        $banner->title = array_filter(['en' => strip_tags($data['title_en']), 'so' => isset($data['title_so']) ? strip_tags($data['title_so']) : null]);
        $banner->link_url = $data['link_url'] ?? null;
        $banner->enabled = $request->boolean('enabled', true);
        $banner->sort = max(0, (int) ($data['sort'] ?? $banner->sort ?? 0));
        $banner->placement = $data['placement'] ?? $banner->placement ?? 'slider';
        $banner->advertiser = isset($data['advertiser']) ? strip_tags($data['advertiser']) : null;
        // A home-slider banner shows to everyone: aiming only makes sense for paid slots.
        $banner->category_id = $banner->placement === 'slider' ? null : ($data['category_id'] ?? null);
        $banner->city_id = $banner->placement === 'slider' ? null : ($data['city_id'] ?? null);
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

    /** @return array<string, mixed> */
    private function row(Banner $b): array
    {
        return [
            'id' => $b->id, 'title_en' => $b->title['en'] ?? '', 'title_so' => $b->title['so'] ?? null, 'link_url' => $b->link_url,
            'enabled' => $b->enabled, 'sort' => $b->sort, 'image' => $b->imageUrl('small'),
            'starts_at' => $b->starts_at?->toDateString(), 'ends_at' => $b->ends_at?->toDateString(),
            'live' => Banner::active()->whereKey($b->id)->exists(),
            'placement' => $b->placement,
            'advertiser' => $b->advertiser,
            'category_id' => $b->category_id,
            'category' => $b->category?->label,
            'city_id' => $b->city_id,
            'city' => $b->city?->label,
            'clicks' => (int) $b->clicks,
        ];
    }
}
