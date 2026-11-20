<?php

namespace Tests\Feature\Watchdog;

use App\Mail\WatchdogMail;
use App\Models\Ad;
use App\Models\WatchdogProblem;
use App\Support\Watchdog\ErrorCounter;
use App\Support\Watchdog\Probes\Disk;
use App\Support\Watchdog\Probes\Ssl;
use App\Support\Watchdog\Runner;
use App\Support\Watchdog\State;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

/** PR 94: every site-health check makes its problem happen → one alert, then "✅ Fixed" when it is back. */
class WatchdogHealthTest extends TestCase
{
    use RefreshDatabase, WatchdogTesting;

    private string $backups;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWatchdog();
        $this->backups = sys_get_temp_dir().'/iibsom-backups-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->backups);
        $this->disk(50);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backups);
        parent::tearDown();
    }

    private function check(string $group = 'frequent'): void
    {
        app(Runner::class)->run($group);
    }

    private function disk(int $usedPercent): void
    {
        $this->mock(Disk::class, function (MockInterface $m) use ($usedPercent) {
            $m->shouldReceive('space')->andReturn(['free' => (100 - $usedPercent) * 1024 ** 3, 'total' => 100 * 1024 ** 3]);
            $m->shouldReceive('folderSize')->andReturn(850 * 1024 ** 2);
        });
    }

    private function breakDatabase(): void
    {
        config(['database.connections.broken' => array_merge(config('database.connections.mysql'), ['host' => '127.0.0.1', 'port' => 1]), 'watchdog.connection' => 'broken']);
    }

    /** @return list<string> */
    private function open(): array
    {
        return WatchdogProblem::open()->orderBy('key')->pluck('key')->all();
    }

    public function test_database_down_alerts_once_reminds_later_and_says_fixed(): void
    {
        $this->check(); // all good first: the channels are copied to the state file
        $this->breakDatabase();

        $this->check();
        $this->check();
        $this->assertCount(1, $this->telegrams());
        $this->assertStringStartsWith('🔴 The database is down', $this->telegrams()[0]);
        $this->assertNotNull(State::get('db_down'));

        $this->travel(7)->hours();
        $this->check();
        $this->assertCount(2, $this->telegrams());
        $this->assertStringContainsString('Still not fixed: The database is down', $this->telegrams()[1]);

        config(['watchdog.connection' => null]);
        $this->check();
        $this->assertStringStartsWith('✅ Fixed: The database is down', $this->telegrams()[2]);
        $this->assertNull(State::get('db_down'));
        $history = WatchdogProblem::where('key', 'health.database')->firstOrFail();
        $this->assertNotNull($history->fixed_at);
    }

    public function test_up_address_is_light_and_tells_no_details(): void
    {
        $this->get('/up')->assertOk()->assertHeaderMissing('Set-Cookie');

        $this->breakDatabase();
        config(['app.debug' => false]);
        $response = $this->get('/up')->assertStatus(500);
        $this->assertStringNotContainsString('Database not reachable', (string) $response->getContent());
        $this->assertStringNotContainsString('127.0.0.1', (string) $response->getContent());
    }

    public function test_failed_and_waiting_background_jobs(): void
    {
        config(['queue.default' => 'database']);
        DB::table('failed_jobs')->insert(['uuid' => 'a1', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()]);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subMinutes(40)->getTimestamp(), 'created_at' => now()->subMinutes(40)->getTimestamp()]);

        $this->check();
        $this->assertSame(['health.queue:failed', 'health.queue:waiting'], $this->open());

        DB::table('jobs')->delete();
        $this->travel(25)->hours();
        $this->check();
        $this->assertSame([], $this->open());
    }

    public function test_disk_warning_at_80_urgent_at_90_fixed_when_space_is_back(): void
    {
        $this->disk(85);
        $this->check();
        $this->assertSame('warning', WatchdogProblem::open()->first()->level);
        $this->assertStringContainsString('The disk is 85% full', $this->telegrams()[0]);

        $this->disk(95);
        $this->check();
        $this->assertSame('urgent', WatchdogProblem::open()->first()->level);
        $this->assertCount(2, $this->telegrams());

        $this->disk(40);
        $this->check();
        $this->assertSame([], $this->open());
        $this->assertStringStartsWith('✅ Fixed', $this->telegrams()[2]);
    }

    public function test_uploads_limit_for_shared_hosting(): void
    {
        $this->check('daily');
        $this->assertNotContains('health.uploads', $this->open()); // no limit set

        $this->checks(['health.uploads' => ['limit' => 1000]]); // 850 MB of 1000
        $this->check('daily');
        $this->assertSame('warning', WatchdogProblem::where('key', 'health.uploads')->first()->level);
    }

    public function test_backups_not_set_missing_old_small_and_broken(): void
    {
        $this->check('daily');
        $this->assertSame('info', WatchdogProblem::where('key', 'health.backups:not_set')->first()->level);

        config(['watchdog.backup_path' => $this->backups]);
        $this->check('daily');
        $this->assertContains('health.backups:missing', $this->open());

        foreach ([5, 4, 3, 2] as $daysAgo) {
            $this->backup("iibsom-day{$daysAgo}.sql.gz", $daysAgo, 2000);
        }
        $this->check('daily');
        $this->assertContains('health.backups:old', $this->open()); // newest is 2 days old
        $this->assertNotContains('health.backups:missing', $this->open());

        $this->backup('iibsom-today.sql.gz', 0, 200); // tonight's, much smaller
        $this->check('daily');
        $this->assertSame(['health.backups:small'], array_values(array_filter($this->open(), fn ($k) => str_starts_with($k, 'health.backups'))));

        File::put($this->backups.'/iibsom-today.sql.gz', 'not a gzip file');
        touch($this->backups.'/iibsom-today.sql.gz', now()->getTimestamp());
        State::put('backup_tested_at', now()->subDays(8)->getTimestamp()); // a week since the last file test
        $this->check('daily');
        $this->assertContains('health.backups:broken', $this->open());

        $this->backup('iibsom-today.sql.gz', 0, 2000); // a good one again: tested again at once
        $this->check('daily');
        $this->assertSame([], array_values(array_filter($this->open(), fn ($k) => str_starts_with($k, 'health.backups'))));
    }

    private function backup(string $name, int $daysAgo, int $rows): void
    {
        $sql = "-- MySQL dump 10.13\nCREATE TABLE `ads` (id int);\n".str_repeat("INSERT INTO `ads` VALUES (1);\n", $rows);
        file_put_contents($this->backups.'/'.$name, gzencode($sql));
        touch($this->backups.'/'.$name, now()->subDays($daysAgo)->subHour()->getTimestamp());
    }

    public function test_debug_and_environment_on_the_live_site_only(): void
    {
        config(['app.url' => 'http://localhost:8095', 'app.debug' => true]);
        $this->check();
        $this->assertSame([], $this->open());

        config(['app.url' => 'https://iibsom.com']);
        $this->check();
        $this->assertSame(['health.app_settings:debug', 'health.app_settings:env'], $this->open()); // tests run as "testing"
        $this->assertStringContainsString('Debug mode is ON on the live site', implode("\n", $this->telegrams()));

        config(['app.url' => 'https://staging.iibsom.com']);
        $this->check();
        $this->assertSame([], $this->open());
    }

    public function test_page_that_does_not_open_is_urgent_slow_twice_is_a_warning(): void
    {
        $this->pageAnswer = fn () => Http::response('error', 500);
        $this->check();
        $this->assertSame(['health.speed:home:down'], $this->open());

        $this->pageAnswer = function () {
            usleep(60_000); // 60 ms

            return Http::response('ok');
        };
        $this->checks(['health.speed' => ['limit' => 20]]);
        $this->check();
        $this->assertSame([], $this->open());
        $this->check();
        $this->assertSame(['health.speed:home:slow'], $this->open());
    }

    public function test_speed_check_opens_the_newest_live_ad_too(): void
    {
        $this->seed();
        $ad = Ad::factory()->live()->create();
        $this->check();

        Http::assertSent(fn ($r) => $r->url() === $ad->url());
        Http::assertSent(fn ($r) => $r->url() === url('/'));
    }

    public function test_error_spike_and_new_kinds_of_error(): void
    {
        Route::get('/_watchdog-boom', fn () => throw new RuntimeException('secret text owner@iibsom.test'))->middleware('web');
        $this->checks(['health.errors' => ['limit' => 3]]);

        $this->get('/_watchdog-boom')->assertStatus(500);
        $this->check();
        $this->assertStringContainsString('New kind of error: RuntimeException', $this->telegrams()[0]);
        $this->assertStringNotContainsString('secret text', $this->telegrams()[0]); // never the message

        $this->get('/_watchdog-boom');
        $this->get('/_watchdog-boom');
        $this->check();
        $this->assertSame(['health.errors:spike'], $this->open());
        $this->assertCount(2, $this->telegrams()); // the same kind is not told again
        $this->assertStringContainsString('Many errors: 3 in 15 minutes', $this->telegrams()[1]);

        $this->travel(20)->minutes();
        $this->check();
        $this->assertSame([], $this->open());
    }

    public function test_no_guest_ip_is_stored_anywhere(): void
    {
        Route::get('/_watchdog-boom', fn () => throw new RuntimeException('x'))->middleware('web');
        $this->get('/_watchdog-boom', ['REMOTE_ADDR' => '203.0.113.77']);
        $this->check();

        $this->assertNotEmpty(WatchdogProblem::all());
        $this->assertStringNotContainsString('203.0.113.77', WatchdogProblem::all()->toJson());
        $this->assertStringNotContainsString('203.0.113.77', (string) file_get_contents($this->statePath));
        $this->assertStringNotContainsString('203.0.113.77', (string) json_encode([Cache::get('watchdog.errors.known'), ErrorCounter::recent()]));
    }

    public function test_ssl_certificate_ending_soon(): void
    {
        config(['app.url' => 'https://iibsom.com']);
        $ends = now()->addDays(10);
        $this->mock(Ssl::class, function (MockInterface $m) use (&$ends) {
            $m->shouldReceive('expiresAt')->with('iibsom.com')->andReturnUsing(function () use (&$ends) {
                return $ends;
            });
        });

        $this->check('daily');
        $this->assertSame('warning', WatchdogProblem::where('key', 'health.ssl')->first()->level);

        $ends = now()->addDays(2);
        $this->check('daily');
        $this->assertSame('urgent', WatchdogProblem::where('key', 'health.ssl')->first()->level);

        $ends = now()->addDays(90);
        $this->check('daily');
        $this->assertNull(WatchdogProblem::open()->where('key', 'health.ssl')->first());
    }

    public function test_daily_mail_test_only_when_email_is_set_up_and_tells_when_it_fails(): void
    {
        $this->check('daily');
        Mail::assertNothingSent(); // the test mailer ("array") is not a real one

        config(['mail.default' => 'smtp']);
        $this->check('daily');
        Mail::assertSent(WatchdogMail::class, fn (WatchdogMail $m) => str_contains($m->subjectLine, 'daily mail test'));

        // A real mailer that cannot connect.
        Mail::swap(new MailManager(app()));
        config(['mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1]);
        app('mail.manager')->purge('smtp');
        $this->check('daily');
        $this->assertContains('health.mail', $this->open());
        $this->assertStringContainsString('The site cannot send email', implode("\n", $this->telegrams()));
    }

    public function test_heartbeat_ping_to_an_outside_service(): void
    {
        $this->watchdogSettings(['heartbeat_url' => 'https://hc-ping.com/abc-123']);
        $this->check();
        Http::assertSent(fn ($r) => $r->url() === 'https://hc-ping.com/abc-123');

        $this->breakDatabase(); // the service is told "fail" while the database is down
        $this->check();
        Http::assertSent(fn ($r) => $r->url() === 'https://hc-ping.com/abc-123/fail');
    }

    public function test_cron_that_stopped_and_came_back_is_told_once(): void
    {
        State::put('last_run', now()->subMinutes(40)->getTimestamp());
        $this->check();
        $this->check();

        $this->assertCount(1, $this->telegrams());
        $this->assertStringContainsString('The cron stopped for 40 minutes', $this->telegrams()[0]);
    }

    public function test_a_check_turned_off_closes_its_problems_quietly(): void
    {
        $this->disk(95);
        $this->check();
        $this->checks(['health.disk' => ['on' => false]]);
        $this->check();

        $this->assertSame([], $this->open());
        $this->assertSame('off', WatchdogProblem::first()->fixed_how);
        $this->assertCount(1, $this->telegrams()); // no "Fixed": it was not fixed, only not watched
    }

    public function test_checks_never_run_in_a_visitors_request(): void
    {
        $this->seed();
        $this->get('/')->assertOk();
        $this->get('/up')->assertOk();

        Http::assertNothingSent();
        $this->assertSame(0, WatchdogProblem::count());
        $this->assertNull(State::get('last_run'));
    }

    public function test_the_command_runs_both_groups(): void
    {
        $this->artisan('iibsom:watchdog')->expectsOutputToContain('health.database')->assertSuccessful();
        $this->artisan('iibsom:watchdog daily')->expectsOutputToContain('health.backups')->assertSuccessful();
        $this->artisan('iibsom:watchdog nope')->assertExitCode(2);
    }
}
