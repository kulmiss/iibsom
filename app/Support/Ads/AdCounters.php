<?php

namespace App\Support\Ads;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Support\Catalog;
use Illuminate\Database\Eloquent\Builder;

/**
 * Keeps "number of live ads" on categories (and their parent) and cities,
 * shown on the home page. Recounted (cheap, indexed) whenever an ad changes.
 */
final class AdCounters
{
    /** @var array{categories: array<int, true>, cities: array<int, true>}|null ids waiting while deferred() runs */
    private static ?array $waiting = null;

    /**
     * Run many ad changes (bulk approve, bulk delete) and recount each category
     * and city only once at the end. At 100k ads one recount takes ≈0.15 s, so
     * 100 ads one by one took ≈16 s (load test); now it is the same as one.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public static function deferred(callable $work): mixed
    {
        if (self::$waiting !== null) {
            return $work(); // already deferring (nested call)
        }
        self::$waiting = ['categories' => [], 'cities' => []];
        try {
            return $work();
        } finally {
            $waiting = self::$waiting;
            self::$waiting = null;
            self::refreshIds(array_keys($waiting['categories']), array_keys($waiting['cities']));
        }
    }

    public static function refreshFor(Ad $ad): void
    {
        $categoryIds = array_values(array_filter([$ad->category_id, $ad->getOriginal('category_id')]));
        $cityIds = array_values(array_filter([$ad->city_id, $ad->getOriginal('city_id')]));

        if (self::$waiting !== null) {
            foreach ($categoryIds as $id) {
                self::$waiting['categories'][(int) $id] = true;
            }
            foreach ($cityIds as $id) {
                self::$waiting['cities'][(int) $id] = true;
            }

            return;
        }

        self::refreshIds($categoryIds, $cityIds);
    }

    /**
     * @param  list<int|string>  $categoryIds
     * @param  list<int|string>  $cityIds
     */
    private static function refreshIds(array $categoryIds, array $cityIds): void
    {
        if ($categoryIds === [] && $cityIds === []) {
            return;
        }
        $parents = [];
        foreach (Category::whereIn('id', $categoryIds)->get() as $category) {
            self::refreshCategory($category);
            if ($category->parent) {
                $parents[$category->parent->id] = $category->parent;
            }
        }
        foreach ($parents as $parent) {
            self::refreshCategory($parent); // once per parent, even when many children changed
        }

        foreach (array_unique($cityIds) as $cityId) {
            City::whereKey($cityId)->update(['ads_count' => self::live()->where('city_id', $cityId)->count()]);
        }

        app(Catalog::class)->flush(everywhere: false); // counts only: the lists (PR 90)
    }

    public static function refreshCategory(Category $category): void
    {
        $ids = $category->parent_id === null ? $category->children()->pluck('id')->push($category->id) : collect([$category->id]);
        $category->newQuery()->whereKey($category->id)->update(['ads_count' => self::live()->whereIn('category_id', $ids)->count()]);
    }

    /** Recount everything (e.g. after "Show demo data" is switched). */
    public static function refreshAll(): void
    {
        foreach (Category::all() as $category) {
            self::refreshCategory($category);
        }
        foreach (City::pluck('id') as $cityId) {
            City::whereKey($cityId)->update(['ads_count' => self::live()->where('city_id', $cityId)->count()]);
        }
        app(Catalog::class)->flush(everywhere: false); // counts only: the lists (PR 90)
    }

    /**
     * Live and not expired. Demo ads count only while "Show demo data" is ON.
     *
     * @return Builder<Ad>
     */
    private static function live(): Builder
    {
        return Ad::query()->where('status', AdStatus::Live)->when(! feature('site.demo_data'), fn ($q) => $q->where('is_demo', false))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
