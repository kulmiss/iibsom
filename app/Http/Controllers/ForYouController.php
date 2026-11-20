<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\Category;
use App\Search\DatabaseAdSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Cards for "Recommended for you" and "Recently viewed" (decision #17).
 * The browser sends what it remembers; nothing is stored or logged here.
 */
class ForYouController extends Controller
{
    private const LIMIT = 12;

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'c' => ['nullable', 'array', 'max:5'],
            'c.*' => ['integer', 'min:1'],
            'v' => ['nullable', 'array', 'max:12'],
            'v.*' => ['integer', 'min:1'],
        ]);
        $cats = array_values(array_unique(array_map('intval', $data['c'] ?? [])));
        $seen = array_values(array_unique(array_map('intval', $data['v'] ?? [])));

        $recent = $this->recent($seen);
        $recommended = $this->recommended($cats, $seen);

        return response()->json([
            'recommended' => $recommended->isEmpty() ? null : $this->cards($recommended),
            'recent' => $recent->isEmpty() ? null : $this->cards($recent),
        ])->header('Cache-Control', 'private, max-age=120');
    }

    /**
     * Ads they opened, newest first, still live.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Ad>
     */
    private function recent(array $ids): Collection
    {
        if (! $ids) {
            return collect();
        }
        $ads = $this->query()->whereIn('ads.id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $ads->get($id))->filter()->values();
    }

    /**
     * New ads from the categories they look at most (a main category counts
     * for all its sub categories). Ads they already opened are left out.
     * Mixed in turn: 1st from each category, then 2nd from each…
     *
     * @param  list<int>  $cats
     * @param  list<int>  $seen
     * @return Collection<int, Ad>
     */
    private function recommended(array $cats, array $seen): Collection
    {
        if (! $cats) {
            return collect();
        }

        $live = Category::query()->whereIn('id', $cats)->get(['id', 'parent_id'])->keyBy('id');
        $lists = [];
        foreach ($cats as $catId) {
            if (! $live->has($catId)) {
                continue;
            }
            $lists[] = $this->query()
                ->whereNotIn('ads.id', $seen ?: [0])
                ->where(fn ($q) => $q->where('category_id', $catId)->orWhereIn('category_id', Category::where('parent_id', $catId)->select('id')))
                ->latest('published_at')->limit(self::LIMIT)->get();
        }

        $out = collect();
        for ($i = 0; $i < self::LIMIT && $out->count() < self::LIMIT; $i++) {
            foreach ($lists as $list) {
                if (($ad = $list->get($i)) && ! $out->has($ad->id)) {
                    $out->put($ad->id, $ad);
                }
            }
        }

        return $out->take(self::LIMIT)->values();
    }

    /** @return Builder<Ad> */
    private function query(): Builder
    {
        return DatabaseAdSearch::publicQuery()->with(['category:id,slug,icon,parent_id', 'city:id,name,slug', 'cover'])->withPhotoCount()->withCardSeller();
    }

    /** @param Collection<int, Ad> $ads */
    private function cards(Collection $ads): string
    {
        return $ads->map(fn (Ad $ad) => view('ads._card', ['ad' => $ad])->render())->implode('');
    }
}
