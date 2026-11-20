<?php

namespace Tests\Feature\Watchdog;

use App\Mail\WatchdogMail;
use App\Models\AdminActivity;
use App\Models\User;
use App\Models\WatchdogProblem;
use App\Support\Features\FeatureManager;
use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Watchdog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** PR 94: the problem book — alert once, reminder later, "✅ Fixed", quiet hours, mute, history. */
class WatchdogCoreTest extends TestCase
{
    use RefreshDatabase, WatchdogTesting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWatchdog();
    }

    private function watchdog(): Watchdog
    {
        return app(Watchdog::class);
    }

    private function finding(string $level = 'urgent', string $key = 'health.disk'): Finding
    {
        return new Finding($key, $level, 'The disk is 95% full', 'Only 1.0 GB is free.', ['Delete old backups.', 'Ask your host for more space.']);
    }

    public function test_a_problem_is_sent_once_by_email_and_telegram_with_easy_words(): void
    {
        $this->watchdog()->apply('health.disk', [$this->finding()]);
        $this->watchdog()->apply('health.disk', [$this->finding()]);
        $this->watchdog()->apply('health.disk', [$this->finding()]);

        $this->assertSame(1, WatchdogProblem::count());
        $this->assertSame(3, WatchdogProblem::first()->count);

        Mail::assertSent(WatchdogMail::class, 1);
        Mail::assertSent(WatchdogMail::class, fn (WatchdogMail $m) => $m->hasTo('owner@iibsom.test') && $m->subjectLine === 'iibsom 🔴 The disk is 95% full');
        $this->assertCount(1, $this->telegrams());
        $text = $this->telegrams()[0];
        foreach (['🔴 The disk is 95% full', 'What happened: Only 1.0 GB is free.', 'How bad: Urgent', 'What to do first:', '1. Delete old backups.', '2. Ask your host', url('/admin/watchdog')] as $part) {
            $this->assertStringContainsString($part, $text);
        }
        // No secrets in the message: the token only travels in Telegram's own address, the chat id only as its field.
        $this->assertStringNotContainsString(self::TOKEN, $text);
        $this->assertStringNotContainsString(self::CHAT, $text);
        $this->assertStringNotContainsString('owner@iibsom.test', $text);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.telegram.org/bot'.self::TOKEN.'/sendMessage') && $r['chat_id'] === self::CHAT);
    }

    public function test_urgent_reminds_after_the_set_hours_warning_does_not(): void
    {
        $this->watchdogSettings(['reminder_hours' => 6]);
        $this->watchdog()->apply('health.disk', [$this->finding('urgent')]);
        $this->watchdog()->apply('health.queue', [$this->finding('warning', 'health.queue:failed')]);

        $this->travel(5)->hours();
        $this->watchdog()->followUp();
        $this->assertCount(2, $this->telegrams());

        $this->travel(2)->hours();
        $this->watchdog()->followUp();
        $this->assertCount(3, $this->telegrams());
        $this->assertStringContainsString('Still not fixed: The disk is 95% full', $this->telegrams()[2]);

        $this->watchdog()->followUp(); // not again straight away
        $this->assertCount(3, $this->telegrams());
    }

    public function test_fixed_message_when_back_to_normal_and_info_is_never_sent(): void
    {
        $this->watchdog()->apply('health.disk', [$this->finding()]);
        $this->watchdog()->apply('health.backups', [$this->finding('info', 'health.backups:not_set')]);
        $this->assertCount(1, $this->telegrams());

        $this->watchdog()->apply('health.disk', []);
        $this->watchdog()->apply('health.backups', []);
        $this->assertCount(2, $this->telegrams());
        $this->assertStringStartsWith('✅ Fixed: The disk is 95% full', $this->telegrams()[1]);

        $problem = WatchdogProblem::where('key', 'health.disk')->first();
        $this->assertNotNull($problem->fixed_at);
        $this->assertSame('auto', $problem->fixed_how);

        $this->watchdog()->apply('health.disk', []); // nothing more
        $this->assertCount(2, $this->telegrams());
    }

    public function test_each_problem_gets_the_area_of_its_check(): void
    {
        $this->watchdog()->apply('security.logins', [$this->finding('urgent', 'security.logins:staff')]);
        $this->watchdog()->apply('ads.queue', [$this->finding('warning', 'ads.queue')]);
        $this->watchdog()->apply('health.backups', [$this->finding('urgent', 'health.backups:old')]);

        $this->assertSame(['ads.queue' => 'ads', 'health.backups:old' => 'backups', 'security.logins:staff' => 'security'], WatchdogProblem::orderBy('key')->pluck('area', 'key')->all());
    }

    public function test_only_the_parts_that_are_found_stay_open(): void
    {
        $this->watchdog()->apply('health.speed', [$this->finding('urgent', 'health.speed:home:down'), $this->finding('urgent', 'health.speed:ad:down')]);
        $this->watchdog()->apply('health.speed', [$this->finding('urgent', 'health.speed:ad:down')]);

        $this->assertSame(['health.speed:ad:down'], WatchdogProblem::open()->pluck('key')->all());
        // A check whose name starts the same is a different check.
        $this->watchdog()->apply('health.speedy', []);
        $this->assertSame(1, WatchdogProblem::open()->count());
    }

    public function test_quiet_hours_hold_warnings_but_not_urgent(): void
    {
        $this->watchdogSettings(['quiet_on' => true, 'quiet_from' => '22:00', 'quiet_to' => '06:00', 'reminder_hours' => 12]);
        $this->travelTo(now()->timezone('Africa/Mogadishu')->setTime(23, 30)); // inside quiet hours

        $this->watchdog()->apply('health.queue', [$this->finding('warning', 'health.queue:failed')]);
        $this->assertCount(0, $this->telegrams());
        $this->assertTrue(WatchdogProblem::first()->pending_alert);

        $this->watchdog()->apply('health.disk', [$this->finding('urgent')]);
        $this->assertCount(1, $this->telegrams());

        $this->travelTo(now()->timezone('Africa/Mogadishu')->addDay()->setTime(6, 5));
        $this->watchdog()->followUp();
        $this->assertCount(2, $this->telegrams());
        $this->assertStringContainsString('🟠', $this->telegrams()[1]);
        $this->assertFalse(WatchdogProblem::where('key', 'health.queue:failed')->first()->pending_alert);
    }

    public function test_a_held_warning_that_is_fixed_in_the_night_is_never_sent(): void
    {
        $this->watchdogSettings(['quiet_on' => true, 'quiet_from' => '22:00', 'quiet_to' => '06:00']);
        $this->travelTo(now()->timezone('Africa/Mogadishu')->setTime(23, 0));
        $this->watchdog()->apply('health.queue', [$this->finding('warning', 'health.queue:failed')]);
        $this->watchdog()->apply('health.queue', []);
        $this->travelTo(now()->timezone('Africa/Mogadishu')->addDay()->setTime(7, 0));
        $this->watchdog()->followUp();

        $this->assertCount(0, $this->telegrams());
    }

    public function test_getting_worse_is_sent_again(): void
    {
        $this->watchdog()->apply('health.disk', [$this->finding('warning')]);
        $this->watchdog()->apply('health.disk', [$this->finding('warning')]);
        $this->watchdog()->apply('health.disk', [$this->finding('urgent')]);

        $this->assertCount(2, $this->telegrams());
        $this->assertStringStartsWith('🔴', $this->telegrams()[1]);
    }

    public function test_mute_stops_reminders_and_mark_fixed_is_logged(): void
    {
        $owner = User::factory()->create();
        $this->watchdog()->apply('health.disk', [$this->finding()]);
        $problem = WatchdogProblem::first();

        $this->watchdog()->mute($problem, 12, $owner);
        $this->travel(7)->hours();
        $this->watchdog()->followUp();
        $this->assertCount(1, $this->telegrams());

        $this->watchdog()->markFixed($problem->fresh(), $owner);
        $this->assertSame('staff', $problem->fresh()->fixed_how);
        $this->assertSame($owner->id, $problem->fresh()->fixed_by);
        $this->assertSame(['watchdog.muted', 'watchdog.fixed'], AdminActivity::orderBy('id')->pluck('action')->all());

        // Still there at the next check: a new problem, told again.
        $this->watchdog()->apply('health.disk', [$this->finding()]);
        $this->assertSame(2, WatchdogProblem::count());
        $this->assertCount(2, $this->telegrams());
    }

    public function test_an_event_is_sent_once_and_goes_straight_to_history(): void
    {
        $problem = $this->watchdog()->event($this->finding('warning', 'health.scheduler'));

        $this->assertNotNull($problem->fixed_at);
        $this->assertSame('event', $problem->fixed_how);
        $this->assertCount(1, $this->telegrams());
        $this->watchdog()->apply('health.scheduler', []);
        $this->assertCount(1, $this->telegrams()); // no "Fixed" for an event
    }

    public function test_history_is_deleted_after_30_days(): void
    {
        $this->watchdog()->apply('health.disk', [$this->finding()]);
        $this->watchdog()->apply('health.disk', []);
        $this->watchdog()->apply('health.queue', [$this->finding('warning', 'health.queue:failed')]); // open: kept

        $this->travel(29)->days();
        $this->assertSame(0, Watchdog::prune());
        $this->travel(2)->days();
        $this->assertSame(1, Watchdog::prune());
        $this->assertSame(['health.queue:failed'], WatchdogProblem::pluck('key')->all());
    }

    public function test_channels_can_be_off_or_not_set(): void
    {
        $this->watchdogSettings(['telegram_on' => false]);
        $this->watchdog()->apply('health.disk', [$this->finding()]);
        $this->assertCount(0, $this->telegrams());
        Mail::assertSent(WatchdogMail::class, 1);

        $this->watchdogSettings(['telegram_on' => true, 'email_to' => 'not an address']);
        $this->watchdog()->apply('health.queue', [$this->finding('warning', 'health.queue:failed')]);
        $this->assertCount(1, $this->telegrams());
        Mail::assertSent(WatchdogMail::class, 1);
    }

    public function test_a_failing_telegram_never_logs_the_token(): void
    {
        $this->telegramAnswer = fn () => Http::response(['ok' => false], 401);
        $this->watchdog()->apply('health.disk', [$this->finding()]);

        $log = (string) @file_get_contents(storage_path('logs/laravel.log'));
        $this->assertStringNotContainsString(self::TOKEN, $log);
        $this->assertNotSame(self::TOKEN, setting('watchdog.telegram_token')); // kept encrypted
    }

    public function test_only_one_open_row_per_problem(): void
    {
        $this->watchdog()->apply('health.disk', [$this->finding()]);
        $this->expectException(UniqueConstraintViolationException::class);
        WatchdogProblem::create(['key' => 'health.disk', 'area' => 'health', 'level' => 'urgent', 'title' => 'x', 'message' => 'x', 'first_seen_at' => now(), 'last_seen_at' => now()]);
    }

    public function test_switch_off_means_no_checks_and_no_alerts(): void
    {
        app(FeatureManager::class)->set('watchdog', false);
        $this->artisan('iibsom:watchdog')->expectsOutput('Watchdog switch is OFF.')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(0, WatchdogProblem::count());
    }
}
