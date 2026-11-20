<?php

namespace App\Support\Watchdog\Checks;

use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Messages;
use App\Support\Watchdog\State;
use App\Support\Watchdog\Watchdog;
use Illuminate\Support\Carbon;

/**
 * Tells when the cron stopped for a while and came back (a one-time message).
 * When it stops for good nothing here runs: the outside monitor and the heartbeat ping cover that.
 */
final class SchedulerCheck implements Check
{
    public function key(): string
    {
        return 'health.scheduler';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        $last = State::get('last_run');
        if (is_int($last)) {
            $gap = (int) floor((now()->getTimestamp() - $last) / 60);
            if ($gap >= max(1, $limit)) {
                $watchdog->event(new Finding($this->key(), 'warning', "The cron stopped for {$gap} minutes",
                    'No scheduled work ran from '.Messages::time(Carbon::createFromTimestamp($last)).' until now (ads did not expire, alerts were not checked). It runs again now.',
                    ['If it happens again, ask your host whether the cron job is still there.', 'Check the cron line in the Watchdog guide.'],
                    ['minutes' => $gap]));
            }
        }

        return [];
    }
}
