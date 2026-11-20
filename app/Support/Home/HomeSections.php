<?php

namespace App\Support\Home;

use App\Models\Ad;
use App\Models\Area;
use App\Models\City;
use App\Models\Shop;
use App\Models\User;
use App\Search\DatabaseAdSearch;
use App\Support\Cache\PageCache;
use App\Support\Catalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The extra parts of the home page (PR 75). Every part has its own switch, uses only real data,
 * and returns nothing when there is nothing true to show (the page then hides that part).
 * Lists are kept 10 minutes and dropped whenever the page cache is cleared (an ad changes).
 */
class HomeSections
{
    private const TTL = 600;

    private static function key(string $part): string
    {
        return 'home:'.$part.':'.PageCache::version().':'.(int) feature('site.demo_data');
    }

    /**
     * Popular searches under the big search box (switch "home.popular_searches"): the words that most live ads have
     * in their title (made at night from ad titles only, never from what visitors type). Two-word phrases first.
     *
     * @return list<string>
     */
    public static function popularSearches(int $limit = 8): array
    {
        if (! feature('home.popular_searches')) {
            return [];
        }

        /** @var list<string> */
        return Cache::remember(self::key('searches'), self::TTL, function () use ($limit): array {
            // Place names are not things to buy; small words like "healthy" or "full" say nothing alone.
            $skip = array_flip(array_merge(self::QUIET_WORDS, self::placeWords()));
            $rows = DB::table('search_terms')->whereRaw("term NOT REGEXP '[0-9]'")->where('ads', '>=', 2)
                ->orderByDesc('ads')->orderBy('term')->limit(400)->get(['term', 'ads']);

            $candidates = [];
            foreach ($rows as $row) {
                $words = explode(' ', (string) $row->term);
                if (array_filter($words, fn (string $w) => isset($skip[$w]) || mb_strlen($w) < 3) !== []) {
                    continue;
                }
                if (count($words) === 1 && ((int) $row->ads < 3 || mb_strlen($words[0]) < 4)) {
                    continue;
                }
                // A two-word phrase says more, so it gets a head start ("samsung galaxy" before "samsung").
                $candidates[] = ['term' => (string) $row->term, 'words' => $words, 'score' => (int) $row->ads + (count($words) > 1 ? 3.5 : 0)];
            }
            usort($candidates, fn (array $x, array $y) => $y['score'] <=> $x['score'] ?: strcmp($x['term'], $y['term']));

            // Every word is used once, so the chips are all different ("goats", not also "young goats").
            $out = [];
            $used = [];
            foreach ($candidates as $c) {
                if (array_intersect($c['words'], $used) !== []) {
                    continue;
                }
                $out[] = $c['term'];
                $used = array_merge($used, $c['words']);
                if (count($out) >= $limit) {
                    break;
                }
            }

            return $out;
        });
    }

    /** Words that say nothing alone (kept short on purpose; the list only shapes the home chips). */
    private const QUIET_WORDS = ['all', 'and', 'any', 'at', 'available', 'best', 'big', 'cheap', 'clean', 'colours', 'colors', 'for', 'fresh',
        'full', 'good', 'healthy', 'heads', 'home', 'inch', 'many', 'near', 'needed', 'new', 'quality', 'sale', 'set', 'size', 'sizes',
        'small', 'somali', 'the', 'used', 'very', 'with', 'young'];

    /** @return list<string> lowercase city and area names in every language */
    private static function placeWords(): array
    {
        $names = [];
        foreach ([City::query()->pluck('name'), Area::query()->pluck('name')] as $list) {
            foreach ($list as $name) {
                foreach ((array) $name as $text) {
                    foreach (preg_split('/\s+/u', mb_strtolower((string) $text)) ?: [] as $word) {
                        $names[] = $word;
                    }
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Popular places (switch "home.places"): cities with ads when 2+ cities have ads; with one city, its areas
     * with the most ads (only while "More search filters" is ON, because the area filter needs it).
     *
     * @return list<array{label: string, count: int, url: string}>
     */
    public static function places(int $limit = 6): array
    {
        if (! feature('home.places')) {
            return [];
        }

        /** @var list<array{kind: string, slug: string, id: int, name: array<string, string>|string, count: int, city: string}> $rows */
        $rows = Cache::remember(self::key('places'), self::TTL, function () use ($limit): array {
            $cities = array_values(array_filter(app(Catalog::class)->cities(), fn (array $c) => $c['ads_count'] > 0));
            if (count($cities) >= 2) {
                usort($cities, fn (array $a, array $b) => $b['ads_count'] <=> $a['ads_count']);

                return array_map(fn (array $c) => ['kind' => 'city', 'slug' => $c['slug'], 'id' => $c['id'], 'name' => $c['name'], 'count' => $c['ads_count'], 'city' => $c['slug']], array_slice($cities, 0, $limit));
            }
            if (count($cities) !== 1 || ! feature('search.more_filters')) {
                return [];
            }

            $city = $cities[0];
            $counts = DatabaseAdSearch::publicQuery()->where('ads.city_id', $city['id'])->whereNotNull('ads.area_id')
                ->groupBy('ads.area_id')->orderByRaw('COUNT(*) DESC')->limit($limit)
                ->selectRaw('ads.area_id, COUNT(*) as n')->pluck('n', 'area_id');
            if ($counts->count() < 2) {
                return [];
            }
            $areas = Area::whereIn('id', $counts->keys())->where('enabled', true)->get(['id', 'name'])->keyBy('id');

            return $counts->map(fn ($n, $id) => isset($areas[$id]) ? ['kind' => 'area', 'slug' => '', 'id' => (int) $id, 'name' => $areas[$id]->name, 'count' => (int) $n, 'city' => $city['slug']] : null)
                ->filter()->values()->all();
        });

        return array_map(fn (array $r) => [
            'label' => is_array($r['name']) ? translated($r['name']) : (string) $r['name'],
            'count' => $r['count'],
            'url' => $r['kind'] === 'city'
                ? lroute('search', ['city' => $r['city']])
                : lroute('search', ['city' => $r['city'], 'area' => $r['id']]),
        ], $rows);
    }

    /**
     * True numbers for the trust strip (switch "home.trust_strip"). A number is left out while it is too small
     * to say anything (fewer than 10 ads, fewer than 3 sellers).
     *
     * @return array{ads: int, sellers: int, checked: bool}
     */
    public static function facts(): array
    {
        /** @var array{ads: int, sellers: int} $n */
        $n = Cache::remember(self::key('facts'), self::TTL, fn (): array => [
            'ads' => (int) DatabaseAdSearch::publicQuery()->count(),
            'sellers' => (int) DatabaseAdSearch::publicQuery()->distinct()->count('ads.user_id'),
        ]);

        return [
            'ads' => $n['ads'] >= 10 ? $n['ads'] : 0,
            'sellers' => $n['sellers'] >= 3 ? $n['sellers'] : 0,
            // Only claimed while it is true: every new and edited ad waits for a person.
            'checked' => feature('ads.approval') && ! feature('ads.trusted_sellers'),
        ];
    }

    /**
     * Featured (boosted) ads (switch "home.featured", needs paid boosts).
     *
     * @return Collection<int, Ad>
     */
    public static function featured(int $limit = 8): Collection
    {
        if (! feature('home.featured') || ! feature('boosts')) {
            return new Collection;
        }

        /** @var list<int> $ids */
        $ids = Cache::remember(self::key('featured'), self::TTL, fn () => DatabaseAdSearch::publicQuery()
            ->where('ads.boost_level', '>', 0)->where('ads.boosted_until', '>', now())
            ->orderByDesc('ads.boost_level')->orderByDesc('ads.published_at')->limit($limit)
            ->pluck('ads.id')->map(fn ($id) => (int) $id)->all());

        if ($ids === []) {
            return new Collection;
        }

        // Loaded again so an ad whose boost or life ended in the last minutes is left out.
        return DatabaseAdSearch::publicQuery()->whereIn('ads.id', $ids)->where('ads.boosted_until', '>', now())
            ->with(['category:id,slug,icon,parent_id', 'city:id,name,slug', 'cover'])->withPhotoCount()->withSellerStars()->get()
            ->sortBy(fn (Ad $ad) => array_search($ad->id, $ids, true))->values();
    }

    /**
     * Trusted sellers (switch "home.trusted_sellers"): active shops (needs "shops") and verified sellers with
     * live ads (needs "verified_badge" and seller pages). Name, picture/initial, ad count and link only.
     *
     * @return list<array{name: string, initial: string, logo: ?string, ads: int, url: string, shop: bool}>
     */
    public static function trustedSellers(int $limit = 6): array
    {
        if (! feature('home.trusted_sellers')) {
            return [];
        }
        $out = [];

        if (feature('shops')) {
            foreach (Shop::query()->active()->with('user:id,banned_at')->orderBy('name')->limit($limit)->get() as $shop) {
                $ads = (int) DatabaseAdSearch::publicQuery()->where('ads.user_id', $shop->user_id)->count();
                if ($ads > 0 && $shop->user->banned_at === null) {
                    $out[] = ['name' => (string) $shop->name, 'initial' => mb_strtoupper(mb_substr((string) $shop->name, 0, 1)), 'logo' => $shop->logoUrl(), 'ads' => $ads, 'url' => $shop->url(), 'shop' => true];
                }
            }
        }

        if (feature('verified_badge') && feature('sellers.page') && count($out) < $limit) {
            $sellers = User::query()->whereNotNull('verified_at')->whereNull('banned_at')
                ->whereHas('ads', fn ($q) => $q->live()->when(! feature('site.demo_data'), fn ($q) => $q->where('ads.is_demo', false)))
                ->withCount(['ads as live_ads' => fn ($q) => $q->live()->when(! feature('site.demo_data'), fn ($q) => $q->where('ads.is_demo', false))])
                ->orderByDesc('live_ads')->limit($limit - count($out))->get(['id', 'name']);
            foreach ($sellers as $seller) {
                $out[] = ['name' => (string) $seller->name, 'initial' => $seller->initial(), 'logo' => null, 'ads' => (int) $seller->getAttribute('live_ads'), 'url' => lroute('seller.show', ['user' => $seller->id]), 'shop' => false];
            }
        }

        return $out;
    }
}
