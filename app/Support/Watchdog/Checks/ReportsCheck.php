<?php

namespace App\Support\Watchdog\Checks;

use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Watchdog;
use Illuminate\Support\Facades\DB;

/** One ad, or all ads of one seller together, reported many times in 24 hours (signed-in and guest reports). */
final class ReportsCheck implements Check
{
    public function key(): string
    {
        return 'ads.reports';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        $limit = max(2, $limit);
        $since = now()->subDay();
        $found = [];

        $ads = DB::table('reports')->where('created_at', '>=', $since)->where('is_demo', false)
            ->groupBy('ad_id')->havingRaw('count(*) >= ?', [$limit])->selectRaw('ad_id, count(*) as total')->get();
        foreach ($ads as $row) {
            $found[] = new Finding("{$this->key()}:ad:{$row->ad_id}", 'warning', "One ad was reported {$row->total} times",
                "Ad #{$row->ad_id} was reported {$row->total} times in 24 hours (your limit is {$limit}).",
                ['Open the ad and the reports (Admin → Reports).', 'Take it down if the reports are right.'],
                ['reports' => (int) $row->total], 'ad', (int) $row->ad_id);
        }

        $sellers = DB::table('reports')->join('ads', 'ads.id', '=', 'reports.ad_id')
            ->where('reports.created_at', '>=', $since)->where('reports.is_demo', false)
            ->groupBy('ads.user_id')->havingRaw('count(*) >= ? and count(distinct reports.ad_id) > 1', [$limit])
            ->selectRaw('ads.user_id, count(*) as total, count(distinct reports.ad_id) as ads')->get();
        foreach ($sellers as $row) {
            $found[] = new Finding("{$this->key()}:seller:{$row->user_id}", 'warning', "One seller's ads were reported {$row->total} times",
                "{$row->ads} ads of user #{$row->user_id} were reported {$row->total} times in 24 hours.",
                ['Open the seller and their reports.', 'Ban the seller if the reports are right.'],
                ['reports' => (int) $row->total, 'ads' => (int) $row->ads], 'user', (int) $row->user_id);
        }

        return $found;
    }
}
