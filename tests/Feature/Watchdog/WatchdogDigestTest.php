<?php

namespace Tests\Feature\Watchdog;

use App\Mail\WatchdogMail;
use App\Models\Ad;
use App\Models\Category;
use App\Models\User;
use App\Support\Watchdog\Digest;
use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Runner;
use App\Support\Watchdog\State;
use App\Support\Watchdog\Watchdog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** PR 95: the daily digest at 08:00 (Mogadishu) — numbers, open problems, "please check", last week on Mondays. */
class WatchdogDigestTest extends TestCase
{
    use RefreshDatabase, WatchdogTesting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->setUpWatchdog();
    }

    private function at(string $time): void
    {
        $this->travelTo(CarbonImmutable::parse($time, 'Africa/Mogadishu'));
    }

    public function test_sent_once_a_day_at_the_digest_time(): void
    {
        $this->at('2026-11-20 07:55');
        app(Runner::class)->run('digest');
        $this->assertSame([], $this->telegrams());

        $this->at('2026-11-20 08:00');
        app(Runner::class)->run('digest');
        app(Runner::class)->run('digest');
        $this->assertCount(1, $this->telegrams());
        Mail::assertSent(WatchdogMail::class, fn (WatchdogMail $m) => $m->subjectLine === 'iibsom 🟢 Daily digest, 20 Nov');

        $this->at('2026-11-21 08:05');
        app(Runner::class)->run('digest');
        $this->assertCount(2, $this->telegrams());
    }

    public function test_the_owner_chooses_the_time(): void
    {
        $this->watchdogSettings(['digest_time' => '09:30']);
        $this->at('2026-11-20 09:00');
        app(Runner::class)->run('digest');
        $this->assertSame([], $this->telegrams());
        $this->at('2026-11-20 09:31');
        app(Runner::class)->run('digest');
        $this->assertCount(1, $this->telegrams());
    }

    public function test_numbers_of_yesterday_open_problems_and_please_check_items(): void
    {
        $this->at('2026-11-19 10:00');
        Ad::factory()->count(3)->live()->create(['category_id' => Category::where('slug', 'cars')->value('id')]);
        User::factory()->count(2)->create(); // + 3 sellers of the ads above = 5
        $watchdog = app(Watchdog::class);
        $watchdog->apply('health.disk', [new Finding('health.disk', 'urgent', 'The disk is 95% full', 'x')]);
        $watchdog->apply('ads.prices', [new Finding('ads.prices:7', 'info', 'Please check: a very low price (ad #7)', 'x', [], [], 'ad', 7)]);
        $watchdog->event(new Finding('security.staff:staff.signed_in:1', 'info', 'Asha signed in to the admin', 'x'));
        $watchdog->event(new Finding('security.staff:staff.signed_in:2', 'info', 'Asha signed in to the admin', 'x'));

        $this->at('2026-11-20 08:00');
        $body = app(Digest::class)->build(CarbonImmutable::now('Africa/Mogadishu'))['body'];

        foreach ([
            'iibsom daily digest, Fri 20 Nov', 'Yesterday (Thu 19 Nov):', '• New users: 5', '• New ads: 3', 'Top categories: Cars (3)',
            'Top places: Mogadishu (3)', '• Errors: 0', 'Site speed: not measured',
            'Open problems: 🔴 1', '🔴 The disk is 95% full (since 19 Nov 10:00)', 'Please check: 1 item(s)',
            'Happened yesterday:', '• Asha signed in to the admin (2×)', url('/admin/watchdog'),
        ] as $part) {
            $this->assertStringContainsString($part, $body);
        }
        $this->assertStringNotContainsString('Last 7 days', $body); // Mondays only
    }

    public function test_monday_digest_has_last_week(): void
    {
        $this->at('2026-11-23 08:00'); // a Monday
        $body = app(Digest::class)->build(CarbonImmutable::now('Africa/Mogadishu'))['body'];

        $this->assertStringContainsString('Last 7 days:', $body);
        $this->assertStringContainsString('✅ No open problems.', $body);
    }

    public function test_the_digest_email_is_the_daily_mail_test(): void
    {
        config(['mail.default' => 'smtp']);
        $this->at('2026-11-20 08:00');
        app(Runner::class)->run('digest');
        $this->assertTrue(State::get('digest_email')['ok']);

        $this->at('2026-11-21 03:20');
        app(Runner::class)->run('daily');
        Mail::assertSent(WatchdogMail::class, 1); // no second (test) mail: the digest went out
        Mail::assertNotSent(WatchdogMail::class, fn (WatchdogMail $m) => str_contains($m->subjectLine, 'daily mail test'));
    }
}
