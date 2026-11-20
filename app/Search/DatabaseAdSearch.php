<?php

namespace App\Search;

use App\Models\Ad;
use App\Support\Cache\PageCache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** MySQL/MariaDB full-text search + indexed filters. */
class DatabaseAdSearch implements AdSearch
{
    /** @return LengthAwarePaginator<int, Ad> */
    public function search(array $filters, int $perPage = 24, int $page = 1): LengthAwarePaginator
    {
        $query = $this->filtered($filters)->with(['category:id,name,slug,icon,parent_id', 'city:id,name,slug', 'cover'])->withPhotoCount()->withCardSeller();

        // Paid boosts (switch "boosts") come first in the default order: Diamond, VIP, Top.
        if (($filters['sort'] ?? 'newest') === 'newest' && feature('boosts')) {
            $query->orderByDesc('ads.boost_level');
        }
        match ($filters['sort'] ?? 'newest') {
            'cheapest' => $query->orderByRaw('price is null')->orderBy('price'),
            'expensive' => $query->orderByDesc('price'),
            // "Most viewed" needs the view numbers (switch "ad_stats"); without them: newest.
            'popular' => feature('ad_stats') ? $query->orderByDesc('views')->orderByDesc('published_at') : $query->orderByDesc('published_at'),
            default => $query->orderByDesc('published_at'),
        };
        $query->orderByDesc('id');

        $total = fn () => $this->cachedCount($filters, $query);

        return $query->paginate($perPage, ['ads.*'], 'page', $page, $total);
    }

    /** How many ads match (for "Show 120 results" while filters change). Kept in the cache until anything changes. */
    public function count(array $filters): int
    {
        return $this->cachedCount($filters, $this->filtered($filters));
    }

    /**
     * Quick price ranges with how many ads each has, for the current filters without the price.
     * Only ranges that have ads; at most 6. One query (kept in the cache like the counts).
     *
     * @return list<array{min: int, max: ?int, count: int}>
     */
    public function priceBuckets(array $filters): array
    {
        $filters = Arr::except($filters, ['min', 'max', 'sort']);
        $key = 'ad-buckets:'.PageCache::version().':'.(int) feature('site.demo_data').':'.md5((string) json_encode($filters));

        return Cache::remember($key, 600, function () use ($filters) {
            $edges = self::BUCKETS;
            $selects = [];
            foreach ($edges as $i => [$min, $max]) {
                $selects[] = 'SUM(CASE WHEN price >= '.$min.($max !== null ? ' AND price < '.$max : '').' THEN 1 ELSE 0 END) AS b'.$i;
            }
            $row = (array) $this->filtered($filters)->toBase()->whereNotNull('price')->selectRaw(implode(', ', $selects))->first();

            $out = [];
            foreach ($edges as $i => [$min, $max]) {
                $count = (int) ($row['b'.$i] ?? 0);
                if ($count > 0) {
                    $out[] = ['min' => $min, 'max' => $max, 'count' => $count];
                }
            }

            return $out;
        });
    }

    /** Price ranges in US dollars: [from, under] (null = no top). */
    public const BUCKETS = [[0, 100], [100, 500], [500, 1000], [1000, 5000], [5000, 20000], [20000, null]];

    /**
     * All filters, no order and no eager loading (shared by the list, the count and the price ranges).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Ad>
     */
    private function filtered(array $filters): Builder
    {
        $query = self::publicQuery();

        if ($q = trim((string) ($filters['q'] ?? ''))) {
            $this->keyword($query, $q);
        }
        if (! empty($filters['category_ids'])) {
            $query->whereIn('category_id', $filters['category_ids']);
        }
        if (! empty($filters['city_id'])) {
            $query->where('city_id', $filters['city_id']);
        }
        if (! empty($filters['area_id'])) {
            $query->where('area_id', $filters['area_id']);
        }
        if (isset($filters['min'])) {
            $query->where('price', '>=', (int) $filters['min']);
        }
        if (isset($filters['max'])) {
            $query->where('price', '<=', (int) $filters['max']);
        }
        if (! empty($filters['condition'])) {
            $query->where('condition', $filters['condition']);
        }
        // "Posted within": the last 24 hours / 7 days / 30 days.
        if (isset($filters['posted']) && ($hours = self::POSTED[$filters['posted']] ?? null)) {
            $query->where('ads.published_at', '>=', now()->subHours($hours));
        }
        if (! empty($filters['photos'])) {
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('ad_photos')->whereColumn('ad_photos.ad_id', 'ads.id')->where('ad_photos.status', 'ready'));
        }
        // Only sellers with the Verified badge (only while that switch is ON).
        if (! empty($filters['verified']) && feature('verified_badge')) {
            $query->whereIn('ads.user_id', fn ($q) => $q->select('id')->from('users')->whereNotNull('verified_at'));
        }
        // Saved-search alerts: only ads published since the last check.
        if (($filters['published_after'] ?? null) instanceof \DateTimeInterface) {
            $query->where('ads.published_at', '>', $filters['published_after']);
            // A free "move to top" (PR 68) is not a new ad: alerts leave it out.
            $query->where(fn ($q) => $q->whereNull('ads.bumped_at')->orWhere('ads.bumped_at', '<=', $filters['published_after']));
        }
        foreach ($filters['fields'] ?? [] as $key => $value) {
            $this->field($query, $key, $value);
        }

        return $query;
    }

    /** Hours for "posted within". */
    public const POSTED = ['24h' => 24, '7d' => 24 * 7, '30d' => 24 * 30];

    /**
     * @param  array<string, mixed>  $filters
     * @param  Builder<Ad>  $query
     */
    private function cachedCount(array $filters, Builder $query): int
    {
        // Counting all live ads is the slowest part of a list (≈0.2 s at 100k ads, load test).
        // The count is kept until anything changes (every ad save bumps the page-cache version).
        $countKey = 'ad-count:'.PageCache::version().':'.(int) feature('site.demo_data').':'.(int) feature('verified_badge').':'.md5((string) json_encode(Arr::except($filters, ['sort'])));

        return (int) Cache::remember($countKey, 600, fn () => $query->toBase()->getCountForPagination());
    }

    /**
     * Live, not expired, demo only when "Show demo data" is ON.
     *
     * @return Builder<Ad>
     */
    public static function publicQuery(): Builder
    {
        return Ad::query()->live()->when(! feature('site.demo_data'), fn ($q) => $q->where('ads.is_demo', false));
    }

    /** @param  Builder<Ad>  $query */
    private function keyword(Builder $query, string $q): void
    {
        $words = collect(preg_split('/\s+/u', mb_strtolower($q)) ?: [])
            ->map(fn (string $w) => preg_replace('/[^\p{L}\p{N}]+/u', '', $w) ?? '')
            ->filter(fn ($w) => mb_strlen($w) >= 2)->take(8);

        if ($words->isEmpty()) {
            return;
        }

        if (config('iibsom.search_fulltext') && DB::getDriverName() === 'mysql' && $words->every(fn ($w) => mb_strlen($w) >= 3)) {
            // +word* = every word must match (prefix), uses the FULLTEXT index.
            $query->whereFullText(['title', 'description'], $words->map(fn ($w) => '+'.$w.'*')->implode(' '), ['mode' => 'boolean']);

            return;
        }

        foreach ($words as $w) {
            $like = '%'.addcslashes($w, '%_').'%';
            $query->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('description', 'like', $like));
        }
    }

    /** Custom field filters: select = exact, number = "min-max", boolean = yes. */
    /** @param  Builder<Ad>  $query */
    private function field(Builder $query, string $key, mixed $value): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $key) || $value === null || $value === '') {
            return;
        }

        if (is_array($value)) { // number range
            if (isset($value['min']) && $value['min'] !== '') {
                $query->where("fields->{$key}", '>=', (int) $value['min']);
            }
            if (isset($value['max']) && $value['max'] !== '') {
                $query->where("fields->{$key}", '<=', (int) $value['max']);
            }

            return;
        }

        $query->where("fields->{$key}", $value === '1' ? true : (string) $value);
    }
}
