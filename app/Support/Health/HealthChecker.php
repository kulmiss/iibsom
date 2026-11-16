<?php

namespace App\Support\Health;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Checks what works on this server (VPS or shared hosting).
 * Shown on the admin health page and by `php artisan iibsom:health`.
 *
 * Each check returns: status ok|warn|fail, a short message in easy English,
 * and a "fix" hint when something is wrong.
 */
class HealthChecker
{
    public const HEARTBEAT_KEY = 'iibsom.scheduler.heartbeat';

    /** @return list<array{key: string, label: string, status: string, message: string, fix: ?string}> */
    public function run(): array
    {
        return [
            $this->php(),
            $this->hostingMode(),
            $this->database(),
            $this->cache(),
            $this->scheduler(),
            $this->queue(),
            $this->storage(),
            $this->images(),
            $this->debugMode(),
        ];
    }

    public function healthy(): bool
    {
        return collect($this->run())->doesntContain('status', 'fail');
    }

    /** Called every minute by the scheduler. */
    public function beat(): void
    {
        Cache::forever(self::HEARTBEAT_KEY, now()->getTimestamp());
    }

    private function php(): array
    {
        $ok = version_compare(PHP_VERSION, '8.3.0', '>=');

        return $this->result('php', 'PHP version', $ok ? 'ok' : 'fail', 'PHP '.PHP_VERSION, $ok ? null : 'Choose PHP 8.3 or newer in your hosting panel.');
    }

    private function hostingMode(): array
    {
        $mode = config('iibsom.hosting');

        return $this->result('hosting', 'Hosting mode', 'ok', $mode === 'shared' ? 'Shared hosting mode' : 'VPS mode');
    }

    private function database(): array
    {
        try {
            DB::select('select 1');

            return $this->result('database', 'Database', 'ok', 'Connected ('.DB::connection()->getDriverName().')');
        } catch (Throwable) {
            return $this->result('database', 'Database', 'fail', 'Cannot connect to the database.', 'Check DB_HOST, DB_DATABASE, DB_USERNAME and DB_PASSWORD in .env.');
        }
    }

    private function cache(): array
    {
        $store = config('cache.default');

        try {
            $key = 'iibsom.health.'.Str::random(8);
            Cache::put($key, 'yes', 10);
            $ok = Cache::pull($key) === 'yes';
        } catch (Throwable) {
            $ok = false;
        }

        if (! $ok) {
            return $this->result('cache', 'Cache', 'fail', "Cache store \"{$store}\" is not working.", 'On shared hosting set CACHE_STORE=database.');
        }

        $warn = config('iibsom.hosting') === 'vps' && $store !== 'redis';

        return $this->result('cache', 'Cache', $warn ? 'warn' : 'ok', "Working ({$store})", $warn ? 'On a VPS, Redis is faster: CACHE_STORE=redis.' : null);
    }

    private function scheduler(): array
    {
        $last = Cache::get(self::HEARTBEAT_KEY);

        if (! $last) {
            return $this->result('scheduler', 'Scheduler (cron)', 'fail', 'Has never run.', $this->cronHint());
        }

        $age = now()->getTimestamp() - (int) $last;
        if ($age > config('iibsom.heartbeat_max_age')) {
            return $this->result('scheduler', 'Scheduler (cron)', 'fail', "Last run {$age} seconds ago.", $this->cronHint());
        }

        return $this->result('scheduler', 'Scheduler (cron)', 'ok', "Last run {$age} seconds ago.");
    }

    private function queue(): array
    {
        $connection = config('queue.default');

        if ($connection === 'sync') {
            return $this->result('queue', 'Background jobs', 'warn', 'Jobs run during the page request (sync). Pages will be slower.', 'Set QUEUE_CONNECTION=database (shared) or redis (VPS).');
        }

        try {
            $failed = DB::table('failed_jobs')->count();
        } catch (Throwable) {
            $failed = 0;
        }

        $how = config('iibsom.hosting') === 'shared' ? 'run by cron every minute' : 'run by the queue worker';
        $status = $failed > 0 ? 'warn' : 'ok';

        return $this->result('queue', 'Background jobs', $status, "Using \"{$connection}\", {$how}. Failed jobs: {$failed}.", $failed > 0 ? 'Open the failed jobs list and retry them.' : null);
    }

    private function storage(): array
    {
        $disk = config('filesystems.default');

        try {
            $path = 'health/'.Str::random(8).'.txt';
            Storage::disk($disk)->put($path, 'ok');
            Storage::disk($disk)->delete($path);

            return $this->result('storage', 'File storage', 'ok', "Writable ({$disk})");
        } catch (Throwable) {
            return $this->result('storage', 'File storage', 'fail', "Cannot write files to \"{$disk}\".", 'Check folder permissions for storage/ or the R2 keys in .env.');
        }
    }

    private function images(): array
    {
        if (extension_loaded('imagick')) {
            return $this->result('images', 'Photo processing', 'ok', 'Imagick (best quality, AVIF + WebP)');
        }

        if (extension_loaded('gd')) {
            $avif = function_exists('imageavif');

            return $this->result('images', 'Photo processing', 'warn', 'GD'.($avif ? ' (WebP + AVIF)' : ' (WebP only)'), 'Imagick gives better photos. Turn it on in the PHP extensions of your panel if you can.');
        }

        return $this->result('images', 'Photo processing', 'fail', 'No image library found.', 'Turn on the "gd" or "imagick" PHP extension.');
    }

    private function debugMode(): array
    {
        $bad = config('app.debug') && app()->environment('production');

        return $this->result('debug', 'Debug mode', $bad ? 'fail' : 'ok', $bad ? 'Debug is ON in production.' : 'OK', $bad ? 'Set APP_DEBUG=false in .env.' : null);
    }

    private function cronHint(): string
    {
        return 'Add this cron job (every minute): cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1';
    }

    private function result(string $key, string $label, string $status, string $message, ?string $fix = null): array
    {
        return compact('key', 'label', 'status', 'message', 'fix');
    }
}
