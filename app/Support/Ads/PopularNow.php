<?php

namespace App\Support\Ads;

use App\Models\Ad;
use App\Search\DatabaseAdSearch;
use App\Support\Cache\PageCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * "Popular now" on the home page (switch "home.popular_now", needs "ad_stats"):
 * the most viewed live ads published in the last 30 days. Views are counted without
 * any visitor data (see AdStats). The list of ids is kept 10 minutes.
 */
class PopularNow
{
    public const DAYS = 30;

    public const MIN = 4;

    /** @return Collection<int, Ad> */
    public static function ads(int $limit = 8): Collection
    {
        if (! feature('home.popular_now') || ! feature('ad_stats')) {
            return new Collection;
        }

        $key = 'popular-now:'.PageCache::version().':'.(int) feature('site.demo_data').':'.$limit;
        /** @var list<int> $ids */
        $ids = Cache::remember($key, 600, fn () => DatabaseAdSearch::publicQuery()
            ->where('ads.published_at', '>=', now()->subDays(self::DAYS))
            ->where('ads.views', '>', 0)
            ->orderByDesc('ads.views')->orderByDesc('ads.id')
            ->limit($limit)->pluck('ads.id')->map(fn ($id) => (int) $id)->all());

        if (count($ids) < self::MIN) {
            return new Collection;
        }

        // Load again through the public query: an ad that ended in the last minutes is left out.
        $ads = DatabaseAdSearch::publicQuery()->whereIn('ads.id', $ids)
            ->with(['category:id,slug,icon,parent_id', 'city:id,name,slug', 'cover'])->withPhotoCount()->withCardSeller()->get()
            ->sortBy(fn (Ad $ad) => array_search($ad->id, $ids, true))->values();

        return $ads->count() >= self::MIN ? $ads : new Collection;
    }
}
