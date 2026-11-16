<?php

namespace App\Search;

use App\Models\Ad;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** MySQL/MariaDB full-text search + indexed filters. */
class DatabaseAdSearch implements AdSearch
{
    /** @return LengthAwarePaginator<int, Ad> */
    public function search(array $filters, int $perPage = 24, int $page = 1): LengthAwarePaginator
    {
        $query = self::publicQuery()->with(['category:id,name,slug,icon,parent_id', 'city:id,name,slug', 'cover']);

        if ($q = trim((string) ($filters['q'] ?? ''))) {
            $this->keyword($query, $q);
        }
        if (! empty($filters['category_ids'])) {
            $query->whereIn('category_id', $filters['category_ids']);
        }
        if (! empty($filters['city_id'])) {
            $query->where('city_id', $filters['city_id']);
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
        // Saved-search alerts: only ads published since the last check.
        if (($filters['published_after'] ?? null) instanceof \DateTimeInterface) {
            $query->where('ads.published_at', '>', $filters['published_after']);
        }
        foreach ($filters['fields'] ?? [] as $key => $value) {
            $this->field($query, $key, $value);
        }

        match ($filters['sort'] ?? 'newest') {
            'cheapest' => $query->orderByRaw('price is null')->orderBy('price'),
            'expensive' => $query->orderByDesc('price'),
            default => $query->orderByDesc('published_at'),
        };
        $query->orderByDesc('id');

        return $query->paginate($perPage, ['ads.*'], 'page', $page);
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
