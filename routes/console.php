<?php

use App\Support\Health\HealthChecker;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
| One cron job runs everything (VPS and shared hosting):
|   * * * * * cd /path/to/iibsom && php artisan schedule:run >> /dev/null 2>&1
*/

// Proves the scheduler is running (shown on the admin health page).
Schedule::call(fn () => app(HealthChecker::class)->beat())
    ->name('heartbeat')
    ->everyMinute();

// Shared hosting has no always-running queue worker, so cron works the queue.
if (config('iibsom.hosting') === 'shared') {
    Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
        ->name('shared-queue-worker')
        ->everyMinute()
        ->withoutOverlapping(5);
}

Artisan::command('iibsom:health', function (HealthChecker $health) {
    $icons = ['ok' => '<info>OK  </info>', 'warn' => '<comment>WARN</comment>', 'fail' => '<error>FAIL</error>'];

    foreach ($health->run() as $check) {
        $this->line($icons[$check['status']].'  '.str_pad($check['label'], 20).$check['message']);
        if ($check['fix']) {
            $this->line('      → '.$check['fix']);
        }
    }

    return $health->healthy() ? 0 : 1;
})->purpose('Check what works on this server (database, cache, cron, queue, storage, images)');
