<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\Area;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\City;
use App\Search\AdSearch;
use App\Search\DatabaseAdSearch;
use App\Search\SearchCriteria;
use App\Search\SearchRequest;
use App\Support\Catalog;
use App\Support\Seo\Landing;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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
            'sort' => ['nullable', 'in:newest,cheapest,expensive,popular'],
            'f' => ['nullable', 'array', 'max:20'],
            'page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'area' => ['nullable', 'integer', 'min:1'],
            'posted' => ['nullable', 'in:24h,7d,30d'],
            'photos' => ['nullable', 'in:1'],
            'verified' => ['nullable', 'in:1'],
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

        // An area only counts inside its own (live) city.
        $more = feature('search.more_filters');
        $areas = $city && $more ? Area::where('city_id', $city->id)->where('enabled', true)->orderBy('sort')->get(['id', 'name']) : collect();
        $area = $request->filled('area') ? $areas->firstWhere('id', $request->integer('area')) : null;

        $searchFilters = SearchRequest::basic($request) + [
            'category_ids' => $categoryIds,
            'city_id' => $city?->id,
            'area_id' => $area?->id,
            'posted' => $more ? ($data['posted'] ?? null) : null,
            'photos' => $more && isset($data['photos']),
            'verified' => $more && isset($data['verified']) && feature('verified_badge'),
            'fields' => $fieldFilters,
        ];
        if (! $more && $searchFilters['sort'] === 'popular') {
            $searchFilters['sort'] = 'newest';
        }
        $results = $this->search->search($searchFilters, 24, max(1, $request->integer('page', 1)))->withQueryString();

        // Nothing found (PR 77, switch "search.rich_empty"): the newest ads of this category in this place,
        // without the other filters, so the page still offers something real.
        $fallback = $results->isEmpty() && feature('search.rich_empty')
            ? DatabaseAdSearch::publicQuery()->with(['category', 'city', 'cover'])->withPhotoCount()->withCardSeller()
                ->when($categoryIds, fn ($q) => $q->whereIn('category_id', $categoryIds))
                ->when($city?->id, fn ($q, $cityId) => $q->where('city_id', $cityId))
                ->latest('published_at')->limit(8)->get()
            : collect();

        // A short, true intro for Google and people (switch "seo.intro"): only on the plain first page of a category.
        $plain = Arr::except($request->query(), ['page']) === [];
        $intro = feature('seo.intro') && $category && $brand === null && $plain && $results->currentPage() === 1 && $results->total() > 0
            ? Landing::priceRange($categoryIds, $city?->id)
            : null;

        return view('browse.index', [
            'results' => $results,
            'category' => $category,
            'city' => $city,
            'fields' => $fields,
            'filters' => $data + ['f' => $fieldFilters],
            'area' => $area,
            'areas' => $areas,
            'priceBuckets' => $more ? $this->search->priceBuckets($searchFilters) : [],
            'active' => $this->activeChips($request, $fields, $fieldFilters, $area, $brand, $more),
            'categories' => $this->catalog->categories(),
            'cities' => $this->catalog->cities(),
            'brand' => $brand,
            'brandLinks' => $category && $category->parent_id !== null && $brand === null && feature('seo.landing_pages') && $city !== null
                ? Landing::topBrands($category, $city) : [],
            'cityLinks' => $category && feature('seo.landing_pages') ? Landing::otherCities($city, $this->catalog->cities()) : [],
            'fallback' => $fallback,
            'intro' => $intro,
        ]);
    }

    /**
     * "Show 120 results" while filters change on the phone sheet (JSON, no cookie, cached counts).
     * Same rules as the list: the filters are read the same way by results().
     */
    public function count(Request $request): JsonResponse
    {
        $request->validate(['category' => ['nullable', 'string', 'max:80'], 'city' => ['nullable', 'string', 'max:80'], 'brand' => ['nullable', 'string', 'max:80']]);
        $category = $request->filled('category') ? Category::where('slug', $request->string('category'))->first() : null;
        $city = $request->filled('city') ? City::where('slug', $request->string('city'))->first() : null;
        // Brand pages (/cars/mogadishu/toyota) count only that brand; an unknown brand is ignored.
        $brandLabel = $category && $city && $request->filled('brand') ? (Landing::brands($category)[(string) $request->string('brand')] ?? null) : null;
        $brand = $brandLabel !== null ? ['value' => (string) $request->string('brand'), 'label' => $brandLabel] : null;
        $view = $this->results($request, $category, $city, $brand);
        /** @var LengthAwarePaginator<int, Ad> $results */
        $results = $view->getData()['results'];
        $total = $results->total();

        return response()->json(['count' => $total, 'text' => trans_choice('ads.browse.show_results', $total, ['count' => number_format($total)])])
            ->header('Cache-Control', 'public, max-age=30');
    }

    /**
     * The filters in use, each with a link that removes only it.
     *
     * @param  Collection<int, CategoryField>  $fields
     * @param  array<string, mixed>  $fieldFilters
     * @param  array{value: string, label: string}|null  $brand
     * @return list<array{label: string, url: string}>
     */
    private function activeChips(Request $request, Collection $fields, array $fieldFilters, ?Area $area, ?array $brand, bool $more): array
    {
        $query = Arr::except($request->query(), ['page']);
        $without = fn (string ...$keys) => $request->url().(($q = http_build_query(Arr::except($query, $keys))) !== '' ? '?'.$q : '');
        $chips = [];
        if ($request->filled('q')) {
            $chips[] = ['label' => '“'.$request->string('q').'”', 'url' => $without('q')];
        }
        if ($request->filled('min') || $request->filled('max')) {
            $chips[] = ['label' => __('ads.browse.price').': '.self::range($request->input('min'), $request->input('max'), '$'), 'url' => $without('min', 'max')];
        }
        if ($request->filled('condition')) {
            $chips[] = ['label' => __('ads.form.'.$request->string('condition')), 'url' => $without('condition')];
        }
        if ($area) {
            $chips[] = ['label' => $area->label, 'url' => $without('area')];
        }
        if ($more && $request->filled('posted')) {
            $chips[] = ['label' => __('ads.browse.posted_'.$request->string('posted')), 'url' => $without('posted')];
        }
        if ($more && $request->filled('photos')) {
            $chips[] = ['label' => __('ads.browse.with_photos'), 'url' => $without('photos')];
        }
        if ($more && $request->filled('verified') && feature('verified_badge')) {
            $chips[] = ['label' => __('ads.browse.verified_only'), 'url' => $without('verified')];
        }
        foreach ($fields as $field) {
            $value = $fieldFilters[$field->key] ?? null;
            if ($value === null || $value === '' || ($brand !== null && $field->key === 'brand')) {
                continue;
            }
            $label = match (true) {
                is_array($value) => self::range($value['min'] ?? null, $value['max'] ?? null, '', $field->unit),
                $field->type === 'boolean' => __('ads.form.yes'),
                default => $field->optionList()[(string) $value] ?? (string) $value,
            };
            if ($label === '') {
                continue;
            }
            $rest = $query;
            unset($rest['f'][$field->key]);
            $chips[] = ['label' => $field->label_text.': '.$label, 'url' => $request->url().(($q = http_build_query($rest)) !== '' ? '?'.$q : '')];
        }

        return $chips;
    }

    private static function range(mixed $min, mixed $max, string $prefix, ?string $unit = null): string
    {
        $fmt = fn (mixed $v) => $prefix.number_format((int) $v).($unit ? ' '.$unit : '');
        $hasMin = $min !== null && $min !== '';
        $hasMax = $max !== null && $max !== '';

        return match (true) {
            $hasMin && $hasMax => $fmt($min).' – '.$fmt($max),
            $hasMin => __('ads.browse.from').' '.$fmt($min),
            $hasMax => __('ads.browse.to').' '.$fmt($max),
            default => '',
        };
    }
}
