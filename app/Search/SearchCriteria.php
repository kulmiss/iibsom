<?php

namespace App\Search;

use App\Models\Category;
use App\Models\City;

/**
 * A search kept as plain data (for saved searches): keyword, price, condition,
 * category, city and field filters. Ids, not slugs, so renaming a category
 * does not break a saved search.
 *
 * Since PR 56 also: area, only with photos, verified sellers (missing in older saved searches = not used).
 *
 * @phpstan-type Criteria array{q: ?string, min: ?int, max: ?int, condition: ?string, category_id: ?int, city_id: ?int, fields: array<string, string>, area_id?: ?int, photos?: bool, verified?: bool}
 */
final class SearchCriteria
{
    /**
     * Ads of a category: a main category means all its live sub categories.
     *
     * @return list<int>|null
     */
    public static function categoryIds(?Category $category): ?array
    {
        if ($category === null) {
            return null;
        }

        return $category->parent_id === null
            ? SearchRequest::ids($category->children()->where('enabled', true)->pluck('id'))
            : [$category->id];
    }

    /**
     * Filters for AdSearch. Returns null when the category or city is gone or
     * switched OFF: then the search would show everything, so it is skipped.
     *
     * @param  Criteria  $criteria
     * @return array{q: ?string, min: ?int, max: ?int, condition: ?string, category_ids: ?list<int>, city_id: ?int, area_id: ?int, photos: bool, verified: bool, fields: array<string, string>, sort: string}|null
     */
    public static function toFilters(array $criteria): ?array
    {
        $category = null;
        if ($criteria['category_id'] !== null) {
            $category = Category::find($criteria['category_id']);
            if ($category === null || ! $category->isLive()) {
                return null;
            }
        }
        if ($criteria['city_id'] !== null && ! City::query()->enabled()->whereKey($criteria['city_id'])->exists()) {
            return null;
        }

        return [
            'q' => $criteria['q'],
            'min' => $criteria['min'],
            'max' => $criteria['max'],
            'condition' => $criteria['condition'],
            'category_ids' => self::categoryIds($category),
            'city_id' => $criteria['city_id'],
            'area_id' => $criteria['area_id'] ?? null,
            'photos' => (bool) ($criteria['photos'] ?? false),
            'verified' => (bool) ($criteria['verified'] ?? false),
            'fields' => $criteria['fields'],
            'sort' => 'newest',
        ];
    }

    /** @param  Criteria  $criteria */
    public static function hash(array $criteria): string
    {
        $fields = $criteria['fields'];
        ksort($fields);
        $criteria['fields'] = $fields;
        $criteria['q'] = $criteria['q'] === null ? null : mb_strtolower($criteria['q']);
        // Not set = the same as an older saved search without these keys.
        foreach (['area_id' => null, 'photos' => false, 'verified' => false] as $key => $empty) {
            if (($criteria[$key] ?? $empty) === $empty) {
                unset($criteria[$key]);
            }
        }

        return hash('sha256', (string) json_encode($criteria));
    }
}
