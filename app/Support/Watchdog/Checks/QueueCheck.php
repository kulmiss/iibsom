<?php

namespace App\Support\Watchdog\Checks;

use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Watchdog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/** Background jobs (photos, emails) wait too long, or failed in the last 24 hours. */
final class QueueCheck implements Check
{
    public function key(): string
    {
        return 'health.queue';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        $found = [];
        $connection = (string) config('queue.default');
        $driver = config("queue.connections.{$connection}.driver");

        if ($driver === 'database') {
            $oldest = DB::table(config("queue.connections.{$connection}.table", 'jobs'))->whereNull('reserved_at')->min('available_at');
            $minutes = $oldest ? (int) floor((now()->getTimestamp() - (int) $oldest) / 60) : 0;
            if ($minutes >= max(1, $limit)) {
                $found[] = new Finding($this->key().':waiting', 'warning', 'Background jobs are waiting',
                    "A background job (photos, emails) has waited {$minutes} minutes. New ad photos may not show.",
                    [config('iibsom.hosting') === 'shared' ? 'Check that the cron job runs every minute.' : 'Check that the queue worker runs (supervisor).', 'Open Admin → Health.'],
                    ['minutes' => $minutes]);
            }
        } elseif ($driver === 'redis') {
            $size = Queue::size();
            if ($size >= 1000) {
                $found[] = new Finding($this->key().':waiting', 'warning', 'Background jobs are waiting',
                    "{$size} background jobs are waiting. New ad photos may not show.",
                    ['Check that the queue worker runs (supervisor).', 'Open Admin → Health.'], ['jobs' => $size]);
            }
        }

        $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
        if ($failed > 0) {
            $found[] = new Finding($this->key().':failed', 'warning', "{$failed} background job(s) failed",
                "{$failed} background job(s) failed in the last 24 hours (for example a photo that could not be made).",
                ['On the server run: php artisan queue:failed', 'Retry them: php artisan queue:retry all'], ['failed' => $failed]);
        }

        return $found;
    }
}
