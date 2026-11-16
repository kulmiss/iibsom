<?php

namespace App\Http\Controllers\Ads;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Area;
use App\Models\Category;
use App\Support\Ads\AdForm;
use App\Support\Catalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            'ad' => null,
            'photos' => [],
            'category' => $category,
            'fields' => $category->allFields(),
            'cities' => $this->catalog->cities(),
            'areas' => $this->areas(),
            'maxPhotos' => setting('ads.max_photos'),
        ]);
    }

    public function store(Request $request, Category $category, AdForm $form): RedirectResponse
    {
        $this->ensurePostable($category);
        $user = $this->authUser($request);

        $this->checkDailyLimit($user->id);
        $ad = $form->create($request, $category, $form->validate($request, $category));

        Log::info('ad.created', ['ad_id' => $ad->id, 'user_id' => $user->id]);

        return redirect(lroute('post.done', ['ad' => $ad->id]));
    }

    public function done(Request $request, Ad $ad): View
    {
        abort_unless($ad->user_id === $this->authUser($request)->id, 404);

        return view('ads.post-done', ['ad' => $ad]);
    }

    public function ensurePostable(Category $category): void
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

    /** @return array<int, array<int, array{id:int, name:string}>> city id => areas */
    public function areas(): array
    {
        return Area::query()->where('enabled', true)->whereIn('city_id', array_column($this->catalog->cities(), 'id'))
            ->orderBy('sort')->get(['id', 'city_id', 'name'])
            ->groupBy('city_id')->map(fn ($areas) => $areas->map(fn ($a) => ['id' => $a->id, 'name' => $a->label])->values()->all())
            ->all();
    }
}
