<?php

namespace App\Http\Controllers;

use App\Support\Catalog;
use App\Support\Search\SearchTerms;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Suggestions while typing a search (switch "search.suggestions"): matching categories and words.
 * Stateless (no cookie, nothing stored about the visitor); answers are cached for 10 minutes.
 */
class SearchSuggestController extends Controller
{
    public function __invoke(Request $request, Catalog $catalog): JsonResponse
    {
        $q = $request->query('q');
        $q = is_string($q) ? Str::lower(trim(Str::limit($q, 40, ''))) : ''; // ?q[]=x is not a search
        $scope = $request->query('in');
        $scope = is_string($scope) ? $scope : '';
        if (! preg_match('/^[a-z0-9-]{1,60}$/', $scope)) {
            $scope = ''; // not a category address: ignore it (also keeps the cache keys small)
        }
        $locale = app()->getLocale();

        $data = Cache::remember('suggest:'.sha1($locale.'|'.$q.'|'.$scope), 600, function () use ($q, $scope, $catalog) {
            if (mb_strlen($q) < 2) {
                return ['terms' => [], 'categories' => [], 'scope' => null];
            }

            // Categories whose name (in this language or in English) has a word starting with what was typed.
            $categories = [];
            $scoped = null;
            foreach ($catalog->categories() as $main) {
                foreach (array_merge([$main], $main['children']) as $c) {
                    if ($c['slug'] === $scope) {
                        $scoped = $c;
                    }
                    if ((int) $c['ads_count'] === 0) {
                        continue; // an empty category is no help as a suggestion
                    }
                    $names = array_unique([Str::lower(translated($c['name'])), Str::lower((string) ($c['name']['en'] ?? ''))]);
                    foreach ($names as $name) {
                        if (preg_match('/(^|[\s&\/-])'.preg_quote($q, '/').'/u', $name)) {
                            $categories[$c['slug']] = ['name' => translated($c['name']), 'url' => lroute('browse.category', ['category' => $c['slug']]), 'count' => (int) $c['ads_count']];
                            break;
                        }
                    }
                }
            }
            uasort($categories, fn ($a, $b) => $b['count'] <=> $a['count']);

            return [
                'terms' => array_map(fn (string $t) => ['text' => $t, 'url' => lroute('search', ['q' => $t])], SearchTerms::suggest($q)),
                'categories' => array_values(array_slice($categories, 0, 4)),
                // "toyota in Cars": search only inside the category the visitor is in.
                'scope' => $scoped ? ['text' => __('site.search.in_scope', ['text' => $q, 'category' => translated($scoped['name'])]), 'url' => lroute('browse.category', ['category' => $scoped['slug'], 'q' => $q])] : null,
            ];
        });

        return response()->json($data)->header('Cache-Control', 'public, max-age=300');
    }
}
