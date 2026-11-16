<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\City;
use App\Search\AdSearch;
use App\Support\Catalog;
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

    private function results(Request $request, ?Category $category, ?City $city): View
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
        $categoryIds = null;
        if ($category) {
            $categoryIds = $category->parent_id === null
                ? $category->children()->where('enabled', true)->pluck('id')->all()
                : [$category->id];
        }

        // Only filters the category really has (and that are marked "filterable").
        $fields = $category && $category->parent_id ? $category->allFields()->where('filterable', true)->values() : collect();
        $fieldFilters = collect($data['f'] ?? [])->only($fields->pluck('key'))->all();

        $results = $this->search->search([
            'q' => $data['q'] ?? null,
            'category_ids' => $categoryIds,
            'city_id' => $city?->id,
            'min' => $data['min'] ?? null,
            'max' => $data['max'] ?? null,
            'condition' => $data['condition'] ?? null,
            'fields' => $fieldFilters,
            'sort' => $data['sort'] ?? 'newest',
        ], 24, (int) ($data['page'] ?? 1))->withQueryString();

        return view('browse.index', [
            'results' => $results,
            'category' => $category,
            'city' => $city,
            'fields' => $fields,
            'filters' => $data + ['f' => $fieldFilters],
            'categories' => $this->catalog->categories(),
            'cities' => $this->catalog->cities(),
        ]);
    }
}
