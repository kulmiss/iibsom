<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\City;
use App\Models\SavedSearch;
use App\Search\SearchCriteria;
use App\Support\Catalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Saved searches (Phase 2, switch "saved_searches"): a daily email lists new matching ads. */
class SavedSearchController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.searches', [
            'searches' => $this->authUser($request)->savedSearches()->latest()->get(),
        ]);
    }

    /** "Save this search" on a results page: the form sends the page's own filters. */
    public function store(Request $request, Catalog $catalog): RedirectResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'min' => ['nullable', 'integer', 'min:0'],
            'max' => ['nullable', 'integer', 'min:0'],
            'condition' => ['nullable', 'in:new,used'],
            'category' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'f' => ['nullable', 'array', 'max:20'],
            'f.*' => ['nullable', 'string', 'max:100'],
        ]);
        $user = $this->authUser($request);

        $category = filled($data['category'] ?? null) ? Category::where('slug', $data['category'])->first() : null;
        $category = $category?->isLive() ? $category : null;
        $city = filled($data['city'] ?? null) ? City::where('slug', $data['city'])->first() : null;
        $city = $city && in_array($city->id, array_column($catalog->cities(), 'id'), true) ? $city : null;

        // Only filters the category really has.
        $keys = $category && $category->parent_id ? $category->allFields()->where('filterable', true)->pluck('key')->all() : [];
        $fields = array_filter(array_intersect_key((array) ($data['f'] ?? []), array_flip($keys)), fn ($v) => is_string($v) && $v !== '');

        $criteria = [
            'q' => filled($data['q'] ?? null) ? trim((string) $data['q']) : null,
            'min' => isset($data['min']) ? (int) $data['min'] : null,
            'max' => isset($data['max']) ? (int) $data['max'] : null,
            'condition' => $data['condition'] ?? null,
            'category_id' => $category?->id,
            'city_id' => $city?->id,
            'fields' => $fields,
        ];
        if ($criteria['q'] === null && $criteria['category_id'] === null) {
            return back()->with('status', __('ads.searches.too_wide'));
        }

        $hash = SearchCriteria::hash($criteria);
        if ($user->savedSearches()->where('hash', $hash)->exists()) {
            return back()->with('status', __('ads.searches.already'));
        }
        if ($user->savedSearches()->count() >= SavedSearch::MAX_PER_USER) {
            return back()->with('status', __('ads.searches.full', ['max' => SavedSearch::MAX_PER_USER]));
        }

        $user->savedSearches()->create([
            'label' => $this->label($criteria, $category, $city),
            'criteria' => $criteria,
            'hash' => $hash,
            'checked_until' => now(), // only ads published from now on are new
        ]);

        return back()->with('status', __('ads.searches.saved'));
    }

    public function destroy(Request $request, SavedSearch $search): RedirectResponse
    {
        abort_unless($search->user_id === $this->authUser($request)->id, 404);
        $search->delete();

        return redirect(lroute('searches'))->with('status', __('ads.searches.deleted'));
    }

    /** @param  array{q: ?string, min: ?int, max: ?int, condition: ?string, category_id: ?int, city_id: ?int, fields: array<string, string>}  $c */
    private function label(array $c, ?Category $category, ?City $city): string
    {
        $parts = array_filter([
            $c['q'] !== null ? '"'.$c['q'].'"' : null,
            $category?->label,
            $city?->label,
            ...array_values($c['fields']),
            $c['min'] !== null || $c['max'] !== null ? '$'.($c['min'] ?? 0).'–'.($c['max'] !== null ? '$'.$c['max'] : '…') : null,
        ]);

        return mb_substr(implode(' · ', $parts), 0, 150);
    }
}
