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

    public function create(Request $request, Category $category): View
    {
        $this->ensurePostable($category);

        return view('ads.post-form', [
            'ad' => null,
            'prefill' => $this->likeThis($request, $category),
            'photos' => [],
            'category' => $category,
            'fields' => $category->allFields(),
            'cities' => $this->catalog->cities(),
            'areas' => $this->areas(),
            'maxPhotos' => setting('ads.max_photos'),
        ]);
    }

    /**
     * "Post an ad like this" (PR 82, switch "ads.post_like_this"): ?like=<live ad of this category> fills in the
     * condition and the pick-from-a-list details (brand, model, …). Never the title, text, price, photos or contact.
     *
     * @return array{condition: ?string, fields: array<string, string>}
     */
    private function likeThis(Request $request, Category $category): array
    {
        $empty = ['condition' => null, 'fields' => []];
        if (! feature('ads.post_like_this') || ! $request->filled('like')) {
            return $empty;
        }
        $like = Ad::query()->live()->whereKey($request->integer('like'))->where('category_id', $category->id)->first();
        if ($like === null) {
            return $empty;
        }
        $fields = [];
        foreach ($category->allFields() as $field) {
            $value = $like->fields[$field->key] ?? null;
            if ($field->type === 'select' && is_scalar($value) && array_key_exists((string) $value, $field->optionList())) {
                $fields[$field->key] = (string) $value;
            }
        }

        return ['condition' => $category->has_condition ? $like->condition : null, 'fields' => $fields];
    }

    public function store(Request $request, Category $category, AdForm $form): RedirectResponse
    {
        $this->ensurePostable($category);
        $user = $this->authUser($request);

        AdForm::checkDailyLimit($user->id);
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

    /** @return array<int, array<int, array{id:int, name:string}>> city id => areas */
    public function areas(): array
    {
        return Area::query()->where('enabled', true)->whereIn('city_id', array_column($this->catalog->cities(), 'id'))
            ->orderBy('sort')->get(['id', 'city_id', 'name'])
            ->groupBy('city_id')->map(fn ($areas) => $areas->map(fn ($a) => ['id' => $a->id, 'name' => $a->label])->values()->all())
            ->all();
    }
}
