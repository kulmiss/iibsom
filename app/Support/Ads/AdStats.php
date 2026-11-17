<?php

namespace App\Support\Ads;

use App\Models\Ad;
use App\Support\Privacy\Bots;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Privacy-safe ad stats (switch "ad_stats"): only a number per ad per day.
 * Nothing about the visitor is kept (no IP, no user, no cookie).
 * Bots and the seller's own visits are not counted.
 */
class AdStats
{
    public const EVENTS = ['views', 'phone', 'whatsapp', 'call'];

    /** Rows older than this are deleted (iibsom:ad-stats:prune). */
    public const KEEP_DAYS = 400;

    public static function record(Ad $ad, string $event, Request $request): bool
    {
        if (! feature('ad_stats') || ! in_array($event, self::EVENTS, true) || ! $ad->isLive()) {
            return false;
        }
        if (Bots::is($request->userAgent())) {
            return false;
        }
        if ($request->user() !== null && $request->user()->id === $ad->user_id) {
            return false; // the seller looking at their own ad
        }

        $row = ['ad_id' => $ad->id, 'day' => self::today()->toDateString()] + array_fill_keys(self::EVENTS, 0);
        $row[$event] = 1;
        DB::table('ad_stats')->upsert([$row], ['ad_id', 'day'], [$event => DB::raw("`{$event}` + 1")]);
        if ($event === 'views') {
            // A running total on the ad itself, for "Most viewed" in lists (no updated_at change, no events).
            DB::table('ads')->where('id', $ad->id)->increment('views');
        }

        return true;
    }

    /**
     * Totals for the last $days days, per ad.
     *
     * @param  list<int>  $adIds
     * @return array<int, array{views: int, phone: int, whatsapp: int, call: int}>
     */
    public static function totals(array $adIds, int $days = 30): array
    {
        if ($adIds === []) {
            return [];
        }
        $rows = DB::table('ad_stats')
            ->whereIn('ad_id', $adIds)
            ->where('day', '>', self::today()->subDays($days)->toDateString())
            ->groupBy('ad_id')
            ->selectRaw('ad_id, SUM(views) AS views, SUM(phone) AS phone, SUM(whatsapp) AS whatsapp, SUM(`call`) AS `call`')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->ad_id] = ['views' => (int) $r->views, 'phone' => (int) $r->phone, 'whatsapp' => (int) $r->whatsapp, 'call' => (int) $r->call];
        }

        return $out;
    }

    /**
     * One row per day (oldest first), days without data as zeros.
     *
     * @return list<array{day: Carbon, views: int, phone: int, whatsapp: int, call: int}>
     */
    public static function daily(Ad $ad, int $days = 14): array
    {
        $from = self::today()->subDays($days - 1);
        $rows = DB::table('ad_stats')->where('ad_id', $ad->id)->where('day', '>=', $from->toDateString())->get()
            ->keyBy(fn ($r) => Carbon::parse((string) $r->day)->toDateString());

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $r = $rows->get($day->toDateString());
            $out[] = [
                'day' => $day,
                'views' => (int) ($r->views ?? 0),
                'phone' => (int) ($r->phone ?? 0),
                'whatsapp' => (int) ($r->whatsapp ?? 0),
                'call' => (int) ($r->call ?? 0),
            ];
        }

        return $out;
    }

    public static function prune(): int
    {
        return DB::table('ad_stats')->where('day', '<', self::today()->subDays(self::KEEP_DAYS)->toDateString())->delete();
    }

    private static function today(): Carbon
    {
        return Carbon::now('Africa/Mogadishu')->startOfDay();
    }
}
