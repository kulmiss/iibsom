<?php

namespace App\Support\Watchdog\Checks;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Watchdog;

/** Ads waiting for a staff check longer than the limit (hours). */
final class QueueWaitCheck implements Check
{
    public function key(): string
    {
        return 'ads.queue';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        $limit = max(1, $limit);
        $waiting = Ad::where('status', AdStatus::Pending)->where('is_demo', false)->where('updated_at', '<=', now()->subHours($limit));
        $count = (clone $waiting)->count();
        if ($count === 0) {
            return [];
        }
        $oldest = (clone $waiting)->min('updated_at');
        $hours = $oldest ? (int) floor(now()->diffInHours($oldest, true)) : $limit;

        return [new Finding($this->key(), 'warning', "{$count} ad(s) waiting more than {$limit} hours",
            "{$count} ad(s) have waited for a staff check longer than {$limit} hours (the oldest {$hours} hours). Sellers may give up.",
            ['Open Admin → Ads → Waiting for approval.', 'If there are always too many, add a moderator or turn on "Trusted sellers".'],
            ['ads' => $count, 'oldest_hours' => $hours])];
    }
}
