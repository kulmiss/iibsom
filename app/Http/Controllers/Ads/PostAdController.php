<?php

namespace App\Http\Controllers\Ads;

use App\Enums\AdStatus;
use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\AdPhoto;
use App\Models\Area;
use App\Models\Category;
use App\Support\Ads\FieldRules;
use App\Support\Catalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Post an ad in two quick steps (made for phones):
 *   1. pick a category       /post
 *   2. fill in one form      /post/{category}
 * Every new ad waits for admin approval (decision #9).
 */
class PostAdController extends Controller
{
    public function __construct(private readonly Catalog $catalog) {}

    public function chooseCategory(): View
    {
        return view('ads.post-choose', ['categories' => $this->catalog->categories()]);
    }

    public function create(Category $category): View
    {
        $this->ensurePostable($category);

        return view('ads.post-form', [
            'category' => $category,
            'fields' => $category->allFields(),
            'cities' => $this->catalog->cities(),
            'areas' => $this->areas(),
            'maxPhotos' => setting('ads.max_photos'),
        ]);
    }

    public function store(Request $request, Category $category): RedirectResponse
    {
        $this->ensurePostable($category);
        $user = $request->user();

        $cityIds = array_column($this->catalog->cities(), 'id');
        $data = $request->validate([
            'title' => ['required', 'string', 'min:5', 'max:80'],
            'description' => ['required', 'string', 'min:20', 'max:3000'],
            'price' => ['nullable', 'integer', 'min:0', 'max:999999999', Rule::requiredIf(! $request->boolean('price_negotiable'))],
            'price_negotiable' => ['sometimes', 'boolean'],
            'condition' => $category->has_condition ? ['required', Rule::in(['new', 'used'])] : ['prohibited'],
            'city_id' => ['required', 'integer', Rule::in($cityIds)],
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->where('city_id', (int) $request->input('city_id'))->where('enabled', true)],
            'photos' => ['nullable', 'array', 'max:'.setting('ads.max_photos')],
            'photos.*' => ['integer', 'distinct'],
        ] + FieldRules::rules($category), [], [
            'title' => __('ads.form.title'),
            'description' => __('ads.form.description'),
            'price' => __('ads.form.price'),
            'condition' => __('ads.form.condition'),
            'city_id' => __('ads.form.city'),
            'area_id' => __('ads.form.area'),
        ] + FieldRules::names($category));

        $this->checkDailyLimit($user->id);

        $title = Str::squish(strip_tags($data['title']));
        $description = trim(strip_tags($data['description']));
        $hash = hash('sha256', $user->id.'|'.Str::lower($title).'|'.Str::lower(Str::squish($description)));

        if (Ad::where('user_id', $user->id)->where('content_hash', $hash)->whereIn('status', [AdStatus::Pending, AdStatus::Live])->exists()) {
            throw ValidationException::withMessages(['title' => __('ads.form.duplicate')]);
        }

        $ad = DB::transaction(function () use ($user, $category, $data, $request, $title, $description, $hash) {
            $ad = new Ad([
                'category_id' => $category->id,
                'city_id' => (int) $data['city_id'],
                'area_id' => $data['area_id'] ?? null,
                'title' => $title,
                'description' => $description,
                'price' => isset($data['price']) ? (int) $data['price'] : null,
                'price_negotiable' => $request->boolean('price_negotiable'),
                'condition' => $category->has_condition ? $data['condition'] : null,
                'fields' => FieldRules::clean($category, $request->input('fields', [])),
            ]);
            $ad->user_id = $user->id;
            $ad->slug = Str::limit(Str::slug($title), 100, '') ?: 'ad';
            $ad->content_hash = $hash;
            $ad->status = feature('ads.approval') ? AdStatus::Pending : AdStatus::Live;
            if ($ad->status === AdStatus::Live) {
                $ad->published_at = now();
                $ad->expires_at = feature('ads.expiry') ? now()->addDays(setting('ads.expiry_days')) : null;
            }
            $ad->save();

            // Attach the user's own, not-yet-used photos in the order they chose.
            foreach (array_values($data['photos'] ?? []) as $sort => $photoId) {
                AdPhoto::whereKey($photoId)->where('user_id', $user->id)->whereNull('ad_id')
                    ->update(['ad_id' => $ad->id, 'sort' => $sort]);
            }

            return $ad;
        });

        Log::info('ad.created', ['ad_id' => $ad->id, 'user_id' => $user->id]);

        return redirect(lroute('post.done', ['ad' => $ad->id]));
    }

    public function done(Request $request, Ad $ad): View
    {
        abort_unless($ad->user_id === $request->user()->id, 404);

        return view('ads.post-done', ['ad' => $ad]);
    }

    private function ensurePostable(Category $category): void
    {
        // Only live sub categories can take ads.
        abort_if($category->parent_id === null || ! $category->isLive(), 404);
    }

    private function checkDailyLimit(int $userId): void
    {
        $today = Ad::withTrashed()->where('user_id', $userId)->where('created_at', '>=', now()->subDay())->count();

        if ($today >= setting('ads.max_per_day')) {
            throw ValidationException::withMessages(['title' => __('ads.form.daily_limit', ['count' => setting('ads.max_per_day')])]);
        }
    }

    /** @return array<int, list<array{id:int, name:string}>> city id => areas */
    private function areas(): array
    {
        return Area::query()->where('enabled', true)->whereIn('city_id', array_column($this->catalog->cities(), 'id'))
            ->orderBy('sort')->get(['id', 'city_id', 'name'])
            ->groupBy('city_id')->map(fn ($areas) => $areas->map(fn ($a) => ['id' => $a->id, 'name' => $a->label])->values()->all())
            ->all();
    }
}
