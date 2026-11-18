<?php

namespace App\Support\Seo;

use App\Models\Category;
use App\Models\City;
use App\Search\DatabaseAdSearch;
use Illuminate\Support\Facades\DB;

/**
 * Data for landing pages that Google can find: brands of a category ("Toyota
 * Cars in Mogadishu") and links between category pages of different cities.
 *
 * @phpstan-import-type CityRow from \App\Support\Catalog
 */
final class Landing
{
    /**
     * Brands a sub category offers (its "brand" field), value => label. Empty when it has none.
     *
     * @return array<string, string>
     */
    public static function brands(Category $category): array
    {
        $field = $category->allFields()->firstWhere('key', 'brand');
        if ($field === null || $field->type !== 'select') {
            return [];
        }

        return array_filter($field->optionList(), fn (string $label, string $value) => $value !== 'other', ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Brands with live ads in this category + city, most ads first (max 12).
     *
     * @return list<array{value: string, label: string, count: int}>
     */
    public static function topBrands(Category $category, City $city): array
    {
        $brands = self::brands($category);
        if ($brands === []) {
            return [];
        }

        $rows = DatabaseAdSearch::publicQuery()
            ->where('category_id', $category->id)->where('city_id', $city->id)
            ->selectRaw("json_unquote(json_extract(fields, '$.brand')) as brand, count(*) as total")
            ->groupBy(DB::raw("json_unquote(json_extract(fields, '$.brand'))"))
            ->orderByDesc('total')->limit(12)->toBase()->get()->all();

        $out = [];
        foreach ($rows as $row) {
            $value = (string) ($row->brand ?? '');
            if (isset($brands[$value])) {
                $out[] = ['value' => $value, 'label' => $brands[$value], 'count' => (int) ($row->total ?? 0)];
            }
        }

        return $out;
    }

    /**
     * The other cities that are ON (for "Cars in other cities" links). None when only one city is ON.
     *
     * @param  list<CityRow>  $cities
     * @return list<CityRow>
     */
    public static function otherCities(?City $current, array $cities): array
    {
        if (count($cities) < 2) {
            return [];
        }

        return array_values(array_filter($cities, fn (array $c) => $c['id'] !== $current?->id));
    }

    /**
     * Lowest and highest asking price of the live ads in these categories (and city), for the short intro
     * text on category pages (PR 77). Ads without a price are left out; null when no ad has a price.
     *
     * @param  list<int>|null  $categoryIds
     * @return array{min: int, max: int, priced: int}|null
     */
    public static function priceRange(?array $categoryIds, ?int $cityId): ?array
    {
        $row = DatabaseAdSearch::publicQuery()
            ->when($categoryIds, fn ($q) => $q->whereIn('category_id', $categoryIds))
            ->when($cityId, fn ($q) => $q->where('city_id', $cityId))
            ->whereNotNull('price')->where('price', '>', 0)
            ->toBase()->selectRaw('MIN(price) as lo, MAX(price) as hi, COUNT(*) as n')->first();

        $n = (int) ($row->n ?? 0);

        return $n > 0 ? ['min' => (int) ($row->lo ?? 0), 'max' => (int) ($row->hi ?? 0), 'priced' => $n] : null;
    }
}
