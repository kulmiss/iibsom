<?php

namespace Tests\Feature\Watchdog;

use App\Mail\WatchdogMail;
use App\Models\AdminActivity;
use App\Models\WatchdogProblem;
use App\Support\Features\FeatureManager;
use App\Support\Watchdog\Finding;
use App\Support\Watchdog\State;
use App\Support\Watchdog\Watchdog;
use App\Support\Watchdog\WatchdogSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Admin\AdminTestCase;

/** PR 96: the Watchdog admin API — who may use it, status and cards, fix / mute, history, settings, test alerts. */
class WatchdogAdminTest extends AdminTestCase
{
    use WatchdogTesting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWatchdog();
    }

    private function raise(string $key, string $level, ?string $subject = null, ?int $id = null): WatchdogProblem
    {
        return app(Watchdog::class)->raise(new Finding($key, $level, "Title {$key}", 'What happened.', ['Do this.'], [], $subject, $id));
    }

    /** @return array<string, mixed> */
    private function form(array $changes = []): array
    {
        return array_replace([
            'email_on' => true, 'email_to' => 'owner@iibsom.test', 'telegram_on' => true, 'telegram_chat_id' => self::CHAT,
            'quiet_on' => false, 'quiet_from' => '23:00', 'quiet_to' => '06:00', 'digest_time' => '08:00', 'reminder_hours' => 6,
            'heartbeat_url' => '', 'checks' => ['health.disk' => ['on' => true, 'limit' => 85]],
        ], $changes);
    }

    public function test_only_staff_who_may_manage_the_watchdog_and_only_while_it_is_on(): void
    {
        $this->getJson('/admin/api/watchdog')->assertUnauthorized();
        $this->asStaff('moderator');
        $this->getJson('/admin/api/watchdog')->assertForbidden();

        $this->asStaff();
        $this->getJson('/admin/api/watchdog')->assertOk();
        $this->getJson('/admin/api/me')->assertJsonPath('user.watchdog', true);

        app(FeatureManager::class)->set('watchdog', false);
        $this->getJson('/admin/api/watchdog')->assertNotFound();
        $this->getJson('/admin/api/me')->assertJsonPath('user.watchdog', false);
    }

    public function test_status_cards_problems_and_please_check(): void
    {
        $this->asStaff();
        $this->getJson('/admin/api/watchdog')->assertOk()->assertJsonPath('status', 'ok')->assertJsonCount(4, 'cards');

        State::put('checked.health.disk', now()->getTimestamp());
        $this->raise('health.disk', 'warning');
        $this->raise('security.logins:staff', 'urgent');
        $this->raise('ads.prices:9', 'info', 'ad', 9);

        $res = $this->getJson('/admin/api/watchdog')->assertOk()
            ->assertJsonPath('status', 'urgent')
            ->assertJsonPath('problems.0.key', 'security.logins:staff') // urgent first
            ->assertJsonPath('problems.1.key', 'health.disk')
            ->assertJsonPath('please_check.0.subject_id', 9)
            ->assertJsonPath('channels', ['email' => true, 'telegram' => true]);
        $cards = collect($res->json('cards'))->keyBy('area');
        $this->assertSame('warning', $cards['health']['status']);
        $this->assertNotNull($cards['health']['last_check']);
        $this->assertSame('urgent', $cards['security']['status']);
        $this->assertSame('ok', $cards['ads']['status']); // a "please check" item is not a problem
        $this->assertStringNotContainsString(self::TOKEN, $res->getContent());
    }

    public function test_mark_as_fixed_and_mute_are_logged_with_who(): void
    {
        $owner = $this->asStaff();
        $problem = $this->raise('health.disk', 'urgent');

        $this->postJson("/admin/api/watchdog/problems/{$problem->id}/mute", ['hours' => 5])->assertUnprocessable();
        $this->postJson("/admin/api/watchdog/problems/{$problem->id}/mute", ['hours' => 12])->assertOk();
        $this->assertTrue($problem->fresh()->isMuted());
        $this->assertSame($owner->id, $problem->fresh()->muted_by);

        $this->postJson("/admin/api/watchdog/problems/{$problem->id}/fix")->assertOk();
        $this->postJson("/admin/api/watchdog/problems/{$problem->id}/fix")->assertUnprocessable();
        $this->assertSame($owner->id, $problem->fresh()->fixed_by);
        $this->assertSame(['watchdog.muted', 'watchdog.fixed'], AdminActivity::where('action', 'like', 'watchdog.%')->orderBy('id')->pluck('action')->all());

        $this->getJson('/admin/api/watchdog/history')->assertOk()->assertJsonPath('data.0.fixed_by', $owner->name)->assertJsonPath('data.0.muted_by', $owner->name);
    }

    public function test_history_filters(): void
    {
        $this->asStaff();
        $this->raise('health.disk', 'urgent');
        $this->raise('security.signups', 'warning');
        app(Watchdog::class)->apply('health.disk', []); // fixed: still in the history

        $this->getJson('/admin/api/watchdog/history')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/admin/api/watchdog/history?level=urgent')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.key', 'health.disk');
        $this->getJson('/admin/api/watchdog/history?area=security')->assertJsonPath('data.0.key', 'security.signups');
        $this->getJson('/admin/api/watchdog/history?q=signups')->assertJsonPath('meta.total', 1);
        $this->getJson('/admin/api/watchdog/history?q=100%25')->assertJsonPath('meta.total', 0); // % is a letter, not "anything"
        $this->getJson('/admin/api/watchdog/history?level=bad')->assertUnprocessable();
    }

    public function test_settings_never_send_the_token_and_keep_it_encrypted(): void
    {
        $this->asStaff();
        $res = $this->getJson('/admin/api/watchdog/settings')->assertOk()
            ->assertJsonPath('telegram_token_set', true)
            ->assertJsonPath('options.email_to', 'owner@iibsom.test');
        $this->assertStringNotContainsString(self::TOKEN, $res->getContent());
        $this->assertSame(count(config('watchdog.checks')), count($res->json('checks')));

        $new = '987654321:'.str_repeat('B', 35);
        $this->putJson('/admin/api/watchdog/settings', $this->form(['telegram_token' => $new]))->assertOk();
        $this->assertSame($new, app(WatchdogSettings::class)->telegramToken());
        $this->assertStringNotContainsString($new, (string) setting('watchdog.telegram_token'));

        $this->putJson('/admin/api/watchdog/settings', $this->form())->assertOk(); // empty token = keep the saved one
        $this->assertSame($new, app(WatchdogSettings::class)->telegramToken());

        $this->putJson('/admin/api/watchdog/settings', $this->form(['remove_telegram_token' => true]))->assertOk()->assertJsonPath('telegram_token_set', false);
        $this->assertSame('watchdog.settings', AdminActivity::latest('id')->value('action'));
    }

    public function test_settings_are_checked(): void
    {
        $this->asStaff();
        $this->putJson('/admin/api/watchdog/settings', $this->form([
            'email_to' => 'not-an-email', 'telegram_token' => 'abc', 'telegram_chat_id' => 'hello', 'quiet_from' => '25:00',
            'heartbeat_url' => 'http://hc-ping.com/x', 'reminder_hours' => 0,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['email_to', 'telegram_token', 'telegram_chat_id', 'quiet_from', 'heartbeat_url', 'reminder_hours']);

        $this->putJson('/admin/api/watchdog/settings', $this->form(['checks' => [
            'health.disk' => ['on' => true, 'limit' => 99],    // 90% is always urgent: the warning limit stays under it
            'health.speed' => ['on' => false, 'limit' => 5],   // too small: would warn all the time
            'not.a.check' => ['on' => true, 'limit' => 1],
        ]]))->assertOk();
        $options = app(WatchdogSettings::class)->all();
        $this->assertSame(89, $options['checks']['health.disk']['limit']);
        $this->assertSame(500, $options['checks']['health.speed']['limit']);
        $this->assertFalse($options['checks']['health.speed']['on']);
        $this->assertArrayNotHasKey('not.a.check', (array) setting('watchdog')['checks']);
    }

    public function test_send_test_alert_to_each_channel(): void
    {
        $this->asStaff();
        $this->postJson('/admin/api/watchdog/test', ['channel' => 'telegram'])->assertOk()->assertJsonPath('sent', true);
        $this->assertStringContainsString('Watchdog test alert', $this->telegrams()[0]);
        Mail::assertNothingSent();

        $this->postJson('/admin/api/watchdog/test', ['channel' => 'email'])->assertOk()->assertJsonPath('sent', true);
        Mail::assertSent(WatchdogMail::class, 1);
        $this->assertCount(1, $this->telegrams());

        $this->telegramAnswer = fn () => Http::response(['ok' => false], 401);
        $this->postJson('/admin/api/watchdog/test', ['channel' => 'telegram'])->assertOk()->assertJsonPath('sent', false);

        app(WatchdogSettings::class)->save(['email_to' => ''], null);
        $this->postJson('/admin/api/watchdog/test', ['channel' => 'email'])->assertUnprocessable();
    }
}
