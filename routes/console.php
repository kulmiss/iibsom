<?php

use App\Enums\AdStatus;
use App\Models\Ad;
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

// Live ads past their end date become "expired" (sellers can renew them).
Schedule::command('iibsom:ads:expire')->name('expire-ads')->hourly()->withoutOverlapping();

// Weekly numbers to the owner: Monday 07:00 Mogadishu time (the command never sends a week twice).
Schedule::command('iibsom:weekly-summary')->name('weekly-summary')
    ->weeklyOn(1, '07:00')->timezone('Africa/Mogadishu')->withoutOverlapping();

Artisan::command('iibsom:ads:expire', function () {
    if (! feature('ads.expiry')) {
        $this->info('Ad expiry is switched off.');

        return 0;
    }

    $count = Ad::where('status', AdStatus::Live)
        ->whereNotNull('expires_at')->where('expires_at', '<=', now())
        ->update(['status' => AdStatus::Expired->value, 'updated_at' => now()]);

    $this->info("Expired {$count} ads.");

    return 0;
})->purpose('Mark live ads past their end date as expired');

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

// Saved-search alerts (Phase 2, switch "saved_searches"): new matching ads, once a day.
Schedule::command('iibsom:saved-search-alerts')->name('saved-search-alerts')
    ->dailyAt('08:00')->timezone('Africa/Mogadishu')->withoutOverlapping();
