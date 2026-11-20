<?php

namespace App\Support\Watchdog;

use App\Models\WatchdogProblem;
use App\Support\Health\HealthChecker;
use App\Support\Watchdog\Checks\AppSettingsCheck;
use App\Support\Watchdog\Checks\BackupsCheck;
use App\Support\Watchdog\Checks\BannedCheck;
use App\Support\Watchdog\Checks\CacheCheck;
use App\Support\Watchdog\Checks\Check;
use App\Support\Watchdog\Checks\CodeFilesCheck;
use App\Support\Watchdog\Checks\CopiesCheck;
use App\Support\Watchdog\Checks\DiskCheck;
use App\Support\Watchdog\Checks\DrugsCheck;
use App\Support\Watchdog\Checks\ErrorsCheck;
use App\Support\Watchdog\Checks\LoginsCheck;
use App\Support\Watchdog\Checks\MailCheck;
use App\Support\Watchdog\Checks\PricesCheck;
use App\Support\Watchdog\Checks\QueueCheck;
use App\Support\Watchdog\Checks\QueueWaitCheck;
use App\Support\Watchdog\Checks\ReportsCheck;
use App\Support\Watchdog\Checks\SchedulerCheck;
use App\Support\Watchdog\Checks\SignupsCheck;
use App\Support\Watchdog\Checks\SpeedCheck;
use App\Support\Watchdog\Checks\SpikesCheck;
use App\Support\Watchdog\Checks\SslCheck;
use App\Support\Watchdog\Checks\StaffCheck;
use App\Support\Watchdog\Checks\UploadPhpCheck;
use App\Support\Watchdog\Checks\UploadsCheck;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs a group of checks from the scheduler (PR 94): "frequent" every 5 minutes, "hourly", "daily" once a night.
 * "digest" (every 5 minutes) sends the daily digest once, when it is due (PR 95).
 * The database is checked first: while it is down nothing can be stored, so one urgent alert goes out
 * from the state file (once, then a reminder), and "✅ Fixed" when it is back.
 */
final class Runner
{
    /** @var array<string, list<class-string<Check>>> */
    public const GROUPS = [
        'frequent' => [
            SchedulerCheck::class, CacheCheck::class, AppSettingsCheck::class, ErrorsCheck::class, QueueCheck::class, DiskCheck::class, SpeedCheck::class,
            LoginsCheck::class, StaffCheck::class, SignupsCheck::class, BannedCheck::class,
            DrugsCheck::class, SpikesCheck::class, ReportsCheck::class, QueueWaitCheck::class,
        ],
        'hourly' => [UploadPhpCheck::class, CopiesCheck::class, PricesCheck::class],
        'digest' => [], // no checks: sends the daily digest when it is due (PR 95)
        'daily' => [UploadsCheck::class, BackupsCheck::class, SslCheck::class, MailCheck::class, CodeFilesCheck::class],
    ];

    private const DB_DOWN_REMINDER_HOURS = 6;

    public function __construct(
        private readonly Watchdog $watchdog,
        private readonly WatchdogSettings $settings,
        private readonly Notifier $notifier,
        private readonly HealthChecker $health,
    ) {}

    /** @return array<string, string>  check key → "ok" | "found N" | "off" | "failed" (for the command's output). */
    public function run(string $group): array
    {
        if (! array_key_exists($group, self::GROUPS)) {
            throw new \InvalidArgumentException("Unknown Watchdog group [{$group}].");
        }
        $done = [];

        if ($group === 'digest') {
            try {
                return ['digest' => app(Digest::class)->sendIfDue() ? 'sent' : 'not due'];
            } catch (Throwable $e) {
                Log::warning('watchdog.digest_failed', ['error' => class_basename($e)]); // e.g. the database is down: the frequent run tells

                return ['digest' => 'failed'];
            }
        }

        if ($group === 'frequent') {
            if (! $this->databaseOk()) {
                $this->databaseDown();
                $this->finishFrequent(databaseUp: false);

                return ['health.database' => 'found 1'];
            }
            $this->databaseBack();
            $done['health.database'] = 'ok';
        }

        foreach (self::GROUPS[$group] as $class) {
            $check = app($class);
            $key = $check->key();
            if (! $this->settings->isOn($key)) {
                $this->watchdog->closeQuietly($key);
                $done[$key] = 'off';

                continue;
            }
            try {
                $findings = $check->run($this->watchdog, $this->settings->limit($key));
                $this->watchdog->apply($key, $findings);
                $done[$key] = $findings === [] ? 'ok' : 'found '.count($findings);
            } catch (Throwable $e) {
                Log::warning('watchdog.check_failed', ['check' => $key, 'error' => class_basename($e)]);
                $this->watchdog->apply($key, [new Finding($key.':check_failed', 'warning', 'The "'.(WatchdogSettings::definition($key)['label'] ?? $key).'" check could not run',
                    'The Watchdog could not finish this check ('.class_basename($e).').', ['Look at storage/logs/laravel.log for "watchdog.check_failed".'])]);
                $done[$key] = 'failed';
            }
            State::put("checked.{$key}", now()->getTimestamp());
        }

        if ($group === 'frequent') {
            $this->watchdog->followUp();
            $this->finishFrequent(databaseUp: true);
        }

        return $done;
    }

    /** Called also while the Watchdog switch is OFF, so turning it back on does not look like a stopped cron. */
    public static function markRun(): void
    {
        State::put('last_run', now()->getTimestamp());
    }

    private function finishFrequent(bool $databaseUp): void
    {
        self::markRun();
        State::put('checked.health.database', now()->getTimestamp());
        if ($databaseUp) {
            $this->settings->rememberChannels();
        }
        $this->pingHeartbeat($databaseUp);
    }

    private function databaseOk(): bool
    {
        if (! $this->settingOn('health.database')) {
            return true;
        }

        return $this->health->only('database')['status'] === 'ok';
    }

    private function databaseDown(): void
    {
        $down = (array) State::get('db_down', []);
        $since = (int) ($down['since'] ?? now()->getTimestamp());
        $alerted = (int) ($down['alerted'] ?? 0);
        $reminder = $alerted > 0;
        if ($alerted === 0 || now()->getTimestamp() - $alerted >= self::DB_DOWN_REMINDER_HOURS * 3600) {
            $this->notifier->send(Messages::alert(self::databaseProblem($since), $reminder), WatchdogSettings::channelsFromFile());
            $alerted = now()->getTimestamp();
        }
        State::put('db_down', ['since' => $since, 'alerted' => $alerted]);
    }

    private function databaseBack(): void
    {
        $down = State::get('db_down');
        if (! is_array($down)) {
            return;
        }
        $problem = self::databaseProblem((int) $down['since']);
        $problem->fill(['last_seen_at' => now(), 'fixed_at' => now(), 'fixed_how' => 'auto', 'alerted_at' => now(), 'last_alert_at' => now()]);
        $problem->save(); // into the history now that the database answers
        State::forget('db_down');
        $this->notifier->send(Messages::fixed($problem));
    }

    private static function databaseProblem(int $since): WatchdogProblem
    {
        return new WatchdogProblem([
            'key' => 'health.database', 'area' => 'health', 'level' => 'urgent',
            'title' => 'The database is down',
            'message' => 'The site cannot reach its database. Visitors see an error page and nobody can post or sign in.',
            'steps' => ['Check that MySQL runs (VPS: systemctl status mysql), or ask your host.', 'Check DB_HOST, DB_DATABASE, DB_USERNAME and DB_PASSWORD in .env.', 'Check that the disk is not full.'],
            'count' => 1, 'first_seen_at' => now()->setTimestamp($since), 'last_seen_at' => now(),
        ]);
    }

    /** Tells an outside service (e.g. healthchecks.io) "the cron is alive"; it warns you when the pings stop. */
    private function pingHeartbeat(bool $databaseUp): void
    {
        try {
            $url = $databaseUp ? $this->settings->all()['heartbeat_url'] : (string) State::get('heartbeat_url', '');
            if ($databaseUp) {
                State::put('heartbeat_url', $url);
            }
            if (! str_starts_with($url, 'https://')) {
                return;
            }
            Http::timeout(5)->connectTimeout(3)->get($databaseUp ? $url : rtrim($url, '/').'/fail');
        } catch (Throwable $e) {
            Log::info('watchdog.heartbeat_failed', ['error' => class_basename($e)]);
        }
    }

    private function settingOn(string $key): bool
    {
        try {
            return $this->settings->isOn($key);
        } catch (Throwable) {
            return true;
        }
    }
}
