<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\City;
use App\Search\AdSearch;
use App\Search\SearchCriteria;
use App\Search\SearchRequest;
use App\Support\Catalog;
use App\Support\Seo\Landing;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Search results and category pages (/cars, /cars/mogadishu, /search?q=...). */
class BrowseController extends Controller
{
    public function __construct(private readonly AdSearch $search, private readonly Catalog $catalog) {}

    public function search(Request $request): View
    {
        $category = $request->filled('category') ? Category::where('slug', $request->string('category'))->first() : null;
        $city = $request->filled('city') ? City::where('slug', $request->string('city'))->first() : null;

        return $this->results($request, $category, $city);
    }

    public function category(Request $request, string $category, ?string $city = null): View
    {
        $cat = Category::where('slug', $category)->first();
        abort_unless($cat && $cat->isLive(), 404);

        $place = null;
        if ($city !== null) {
            $place = City::where('slug', $city)->first();
            abort_unless($place && in_array($place->id, array_column($this->catalog->cities(), 'id'), true), 404);
        }

        return $this->results($request, $cat, $place);
    }

    /** /cars/mogadishu/toyota: one brand of a sub category in one city. */
    public function brand(Request $request, string $category, string $city, string $brand): View
    {
        $cat = Category::where('slug', $category)->first();
        $place = City::where('slug', $city)->first();
        abort_unless($cat && $cat->parent_id !== null && $cat->isLive() && $place
            && in_array($place->id, array_column($this->catalog->cities(), 'id'), true), 404);

        $label = Landing::brands($cat)[$brand] ?? null;
        abort_if($label === null, 404);

        return $this->results($request, $cat, $place, ['value' => $brand, 'label' => $label]);
    }

    /** @param  array{value: string, label: string}|null  $brand */
    private function results(Request $request, ?Category $category, ?City $city, ?array $brand = null): View
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'min' => ['nullable', 'integer', 'min:0'],
            'max' => ['nullable', 'integer', 'min:0'],
            'condition' => ['nullable', 'in:new,used'],
            'sort' => ['nullable', 'in:newest,cheapest,expensive'],
            'f' => ['nullable', 'array', 'max:20'],
            'page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        if ($category && ! $category->isLive()) {
            $category = null;
        }
        $liveCityIds = array_column($this->catalog->cities(), 'id');
        if ($city && ! in_array($city->id, $liveCityIds, true)) {
            $city = null;
        }

        // A main category shows the ads of all its live sub categories.
        $categoryIds = SearchCriteria::categoryIds($category);

        // Only filters the category really has (and that are marked "filterable").
        $fields = $category && $category->parent_id ? $category->allFields()->where('filterable', true)->values() : collect();
        $fieldFilters = $request->collect('f')->only($fields->pluck('key'))->all();
        if ($brand !== null) {
            $fieldFilters['brand'] = $brand['value'];
            $fields = $fields->reject(fn ($f) => $f->key === 'brand')->values(); // the page IS the brand
        }

        $results = $this->search->search(SearchRequest::basic($request) + [
            'category_ids' => $categoryIds,
            'city_id' => $city?->id,
            'fields' => $fieldFilters,
        ], 24, max(1, $request->integer('page', 1)))->withQueryString();

        return view('browse.index', [
            'results' => $results,
            'category' => $category,
            'city' => $city,
            'fields' => $fields,
            'filters' => $data + ['f' => $fieldFilters],
            'categories' => $this->catalog->categories(),
            'cities' => $this->catalog->cities(),
            'brand' => $brand,
            'brandLinks' => $category && $category->parent_id !== null && $brand === null && feature('seo.landing_pages') && $city !== null
                ? Landing::topBrands($category, $city) : [],
            'cityLinks' => $category && feature('seo.landing_pages') ? Landing::otherCities($city, $this->catalog->cities()) : [],
        ]);
    }
}
