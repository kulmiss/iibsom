<?php

namespace Tests\Feature;

use App\Mail\WeeklySummaryMail;
use App\Models\Ad;
use App\Models\AdminActivity;
use App\Models\Category;
use App\Models\City;
use App\Support\Features\FeatureManager;
use App\Support\Reports\WeeklySummary;
use App\Support\Settings\SettingsManager;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\Feature\Admin\AdminTestCase;

class WeeklySummaryTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Wednesday 18 Nov 2026, 10:00 Mogadishu → last week = Mon 9 – Mon 16 Nov.
        $this->travelTo(CarbonImmutable::parse('2026-11-18 10:00', 'Africa/Mogadishu'));
    }

    private function ad(string $createdMogadishu, bool $demo = false): Ad
    {
        $ad = Ad::factory()->create([
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
            'is_demo' => $demo,
        ]);
        $ad->forceFill(['created_at' => CarbonImmutable::parse($createdMogadishu, 'Africa/Mogadishu')->utc()])->saveQuietly();

        return $ad;
    }

    public function test_week_is_monday_to_monday_in_mogadishu_time(): void
    {
        $from = WeeklySummary::lastWeek();
        $this->assertSame('2026-11-09 00:00', $from->format('Y-m-d H:i'));
        $this->assertSame('Africa/Mogadishu', $from->getTimezone()->getName());

        // Sunday 23:30 Mogadishu is still "last week", Monday 00:30 is not.
        $this->ad('2026-11-15 23:30');
        $this->ad('2026-11-16 00:30');
        $this->ad('2026-11-08 23:59'); // week before
        $this->ad('2026-11-10 12:00', demo: true); // demo: not counted while the switch is OFF

        $s = WeeklySummary::build($from);
        $this->assertSame(1, $s['week']['new_ads']);
        $this->assertSame(1, $s['previous']['new_ads']);
        $this->assertSame('Cars', $s['top_categories'][0]['name']);
        $this->assertFalse($s['includes_demo']);
    }

    public function test_counts_approvals_from_the_activity_log(): void
    {
        AdminActivity::create(['action' => 'ad.approved', 'summary' => 'x', 'created_at' => CarbonImmutable::parse('2026-11-12 09:00', 'Africa/Mogadishu')->utc()]);
        AdminActivity::create(['action' => 'ad.approved', 'summary' => 'x', 'created_at' => CarbonImmutable::parse('2026-11-12 09:05', 'Africa/Mogadishu')->utc()]);
        AdminActivity::create(['action' => 'ad.rejected', 'summary' => 'x', 'created_at' => CarbonImmutable::parse('2026-11-13 09:00', 'Africa/Mogadishu')->utc()]);

        $s = WeeklySummary::build(WeeklySummary::lastWeek());
        $this->assertSame(2, $s['week']['approved']);
        $this->assertSame(1, $s['week']['rejected']);
    }

    public function test_sends_once_per_week_to_the_saved_addresses(): void
    {
        Mail::fake();
        app(SettingsManager::class)->set('reports.weekly_emails', ['owner@example.com', 'partner@example.com']);
        $this->ad('2026-11-10 12:00');

        $this->artisan('iibsom:weekly-summary')->assertSuccessful();
        Mail::assertSent(WeeklySummaryMail::class, 2);
        Mail::assertSent(WeeklySummaryMail::class, fn ($m) => $m->hasTo('owner@example.com') && $m->summary['week']['new_ads'] === 1);

        $this->artisan('iibsom:weekly-summary')->expectsOutputToContain('Already sent')->assertSuccessful();
        Mail::assertSent(WeeklySummaryMail::class, 2); // not again
        $this->assertSame('2026-W46', setting('reports.weekly_last_sent'));
    }

    public function test_email_content(): void
    {
        $this->ad('2026-11-10 12:00');
        $html = (new WeeklySummaryMail(WeeklySummary::build(WeeklySummary::lastWeek())))->render();

        $this->assertStringContainsString('Monday 9 Nov – Sunday 15 Nov 2026', $html);
        $this->assertStringContainsString('New ads', $html);
        $this->assertStringContainsString('Cars (1)', $html);
        $this->assertStringContainsString('/admin', $html);
    }

    public function test_nothing_when_no_addresses_or_switch_off(): void
    {
        Mail::fake();
        $this->artisan('iibsom:weekly-summary')->expectsOutputToContain('No addresses')->assertSuccessful();

        app(SettingsManager::class)->set('reports.weekly_emails', ['owner@example.com']);
        app(FeatureManager::class)->set('reports.weekly_email', false);
        $this->artisan('iibsom:weekly-summary')->assertSuccessful();
        Mail::assertNothingSent();
    }

    public function test_mail_failure_is_logged_and_others_still_get_it(): void
    {
        app(SettingsManager::class)->set('reports.weekly_emails', ['bad@example.com', 'good@example.com']);
        Log::spy();
        Mail::shouldReceive('to')->with('bad@example.com')->andThrow(new RuntimeException('SMTP down'));
        Mail::shouldReceive('to')->with('good@example.com')->andReturnSelf();
        Mail::shouldReceive('send')->once();

        $this->artisan('iibsom:weekly-summary')->expectsOutputToContain('Could not send to bad@example.com')->assertFailed();
        Log::shouldHaveReceived('error')->withArgs(fn ($msg, $ctx) => $msg === 'weekly_summary.failed' && $ctx['to'] === 'bad@example.com');
        $this->assertSame('2026-W46', setting('reports.weekly_last_sent')); // one got it: don't spam the other next run
    }

    public function test_scheduled_monday_morning_mogadishu(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'iibsom:weekly-summary'));
        $this->assertNotNull($event);
        $this->assertSame('0 7 * * 1', $event->expression);
        $this->assertSame('Africa/Mogadishu', $event->timezone);
    }

    public function test_admin_saves_addresses_and_sends_a_test(): void
    {
        Mail::fake();
        $me = $this->asStaff();
        $base = ['ads_expiry_days' => 60, 'ads_max_per_day' => 5, 'ads_max_photos' => 8, 'languages' => ['en']];

        $this->putJson('/admin/api/settings/general', $base + ['weekly_emails' => ['not-an-email']])->assertUnprocessable();
        $this->putJson('/admin/api/settings/general', $base + ['weekly_emails' => ['Owner@Example.com', 'owner@example.com']])
            ->assertOk()->assertJsonPath('weekly_emails', ['owner@example.com']);

        $this->postJson('/admin/api/settings/weekly-summary/test')->assertOk();
        Mail::assertSent(WeeklySummaryMail::class, fn ($m) => $m->hasTo($me->email));
        $this->assertSame('', setting('reports.weekly_last_sent')); // a test doesn't count as the weekly send
    }

    public function test_support_staff_cannot_change_settings_or_send_tests(): void
    {
        $this->asStaff('support');
        $this->postJson('/admin/api/settings/weekly-summary/test')->assertForbidden();
    }
}
