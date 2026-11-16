<?php

namespace App\Support;

use App\Models\Category;
use App\Models\City;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Fast, cached lists for public pages: live categories (with sub categories)
 * and live cities. Stored as plain arrays so they are cheap to cache.
 * The cache is cleared whenever a category/city/region/area changes.
 */
class Catalog
{
    public const CATEGORIES_KEY = 'iibsom.catalog.categories.v1';

    public const CITIES_KEY = 'iibsom.catalog.cities.v1';

    public function __construct(private readonly Cache $cache) {}

    /**
     * @return list<array{id:int, slug:string, icon:string, name:array, ads_count:int, popular:bool, children:list<array>}>
     */
    public function categories(): array
    {
        return $this->cache->remember(self::CATEGORIES_KEY, config('iibsom.cache_ttl'), function () {
            return Category::query()
                ->topLevel()->enabled()->orderBy('sort')
                ->with(['children' => fn ($q) => $q->enabled()->orderBy('sort')])
                ->get()
                ->map(fn (Category $c) => $this->categoryRow($c) + [
                    'children' => $c->children->map(fn (Category $child) => $this->categoryRow($child))->all(),
                ])
                ->all();
        });
    }

    /** Sub categories marked "popular" (for the home page tiles). */
    public function popularCategories(int $limit = 8): array
    {
        return collect($this->categories())
            ->flatMap(fn (array $parent) => collect($parent['children'])->map(fn ($c) => $c + ['parent_slug' => $parent['slug']]))
            ->where('popular', true)
            ->take($limit)
            ->values()
            ->all();
    }

    /** @return list<array{id:int, slug:string, name:array, popular:bool, ads_count:int}> */
    public function cities(): array
    {
        return $this->cache->remember(self::CITIES_KEY, config('iibsom.cache_ttl'), function () {
            return City::query()->enabled()->orderBy('sort')
                ->get(['id', 'slug', 'name', 'popular', 'ads_count'])
                ->map(fn (City $c) => [
                    'id' => $c->id,
                    'slug' => $c->slug,
                    'name' => $c->name,
                    'popular' => $c->popular,
                    'ads_count' => $c->ads_count,
                ])
                ->all();
        });
    }

    public function flush(): void
    {
        $this->cache->forget(self::CATEGORIES_KEY);
        $this->cache->forget(self::CITIES_KEY);
    }

    private function categoryRow(Category $c): array
    {
        return [
            'id' => $c->id,
            'slug' => $c->slug,
            'icon' => $c->icon,
            'name' => $c->name,
            'ads_count' => $c->ads_count,
            'popular' => $c->popular,
        ];
    }
}
