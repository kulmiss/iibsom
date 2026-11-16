<?php

namespace App\Support\Ads;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Support\Catalog;

/**
 * Keeps "number of live ads" on categories (and their parent) and cities,
 * shown on the home page. Recounted (cheap, indexed) whenever an ad changes.
 */
final class AdCounters
{
    public static function refreshFor(Ad $ad): void
    {
        $categoryIds = array_filter([$ad->category_id, $ad->getOriginal('category_id')]);
        $cityIds = array_filter([$ad->city_id, $ad->getOriginal('city_id')]);

        foreach (Category::whereIn('id', $categoryIds)->get() as $category) {
            self::refreshCategory($category);
            if ($category->parent) {
                self::refreshCategory($category->parent);
            }
        }

        foreach (array_unique($cityIds) as $cityId) {
            City::whereKey($cityId)->update(['ads_count' => self::live()->where('city_id', $cityId)->count()]);
        }

        app(Catalog::class)->flush();
    }

    public static function refreshCategory(Category $category): void
    {
        $ids = $category->parent_id === null ? $category->children()->pluck('id')->push($category->id) : collect([$category->id]);
        $category->newQuery()->whereKey($category->id)->update(['ads_count' => self::live()->whereIn('category_id', $ids)->count()]);
    }

    /** Live, not expired, not demo (demo never counts in real totals). */
    private static function live()
    {
        return Ad::query()->where('status', AdStatus::Live)->where('is_demo', false)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
