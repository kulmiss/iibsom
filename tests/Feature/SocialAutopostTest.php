<?php

namespace Tests\Feature;

use App\Enums\AdStatus;
use App\Jobs\PostAdToSocial;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\SocialPost;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Health\HealthChecker;
use App\Support\Social\SocialPostFailed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SocialAutopostTest extends TestCase
{
    use RefreshDatabase;

    private const TG_TOKEN = '123456:SECRET-telegram-token';

    private const FB_TOKEN = 'EAAB-SECRET-facebook-token';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(FeatureManager::class)->set('social.autopost', true);
        config([
            'services.telegram.bot_token' => self::TG_TOKEN, 'services.telegram.chat_id' => '@iibsom',
            'services.facebook.page_id' => '987', 'services.facebook.page_token' => self::FB_TOKEN,
        ]);
    }

    /** @param array<string, mixed> $attrs */
    private function ad(array $attrs = []): Ad
    {
        return Ad::factory()->create(array_merge([
            'user_id' => User::factory()->completed()->create()->id,
            'title' => 'Toyota Vitz 2012 automatic',
            'price' => 6500,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
            'status' => AdStatus::Pending,
        ], $attrs));
    }

    private function goLive(Ad $ad): void
    {
        $ad->status = AdStatus::Live;
        $ad->published_at = now();
        $ad->save();
    }

    private function fakeOk(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]]),
            'graph.facebook.com/*' => Http::response(['id' => '987_555']),
        ]);
    }

    public function test_off_by_default_and_nothing_queued_when_off(): void
    {
        $this->assertFalse(config('features')['social.autopost']['default']);
        app(FeatureManager::class)->set('social.autopost', false);
        Queue::fake();

        $this->goLive($this->ad());
        Queue::assertNothingPushed();
        $this->assertSame(0, SocialPost::count());
    }

    public function test_shared_once_when_it_first_goes_live_to_each_network(): void
    {
        $this->fakeOk();
        $ad = $this->ad();
        $this->assertSame(0, SocialPost::count(), 'Pending ads are not shared.');

        $this->goLive($ad); // the queue runs at once in tests (sync)
        $this->assertSame(['facebook' => 'posted', 'telegram' => 'posted'], SocialPost::orderBy('network')->pluck('status', 'network')->all());
        $this->assertSame('77', SocialPost::where('network', 'telegram')->value('remote_id'));

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'api.telegram.org/bot'.self::TG_TOKEN.'/sendMessage')
            && $r['chat_id'] === '@iibsom'
            && str_contains($r['text'], 'Toyota Vitz 2012 automatic')
            && str_contains($r['text'], '$6,500 · Mogadishu · Cars')
            && str_contains($r['text'], $ad->url()));
        Http::assertSent(fn (Request $r) => $r->url() === 'https://graph.facebook.com/v21.0/987/feed'
            && $r['link'] === $ad->url() && $r['access_token'] === self::FB_TOKEN
            && ! str_contains($r->url(), self::FB_TOKEN));

        // Edit, expire and renew: never shared again.
        $ad->update(['title' => 'Toyota Vitz 2012 automatic clean']);
        $ad->forceFill(['status' => AdStatus::Expired])->save();
        $this->goLive($ad);
        (new PostAdToSocial($ad->id, 'telegram'))->handle(); // a stray second job does nothing
        Http::assertSentCount(2);
        $this->assertSame(2, SocialPost::count());
    }

    public function test_only_configured_networks_and_never_demo_ads(): void
    {
        $this->fakeOk();
        config(['services.facebook.page_token' => null]);

        $this->goLive($this->ad(['is_demo' => true]));
        $this->assertSame(0, SocialPost::count());

        $this->goLive($this->ad());
        $this->assertSame(['telegram'], SocialPost::pluck('network')->all());
        Http::assertSentCount(1);
    }

    public function test_ad_created_live_is_shared(): void
    {
        $this->fakeOk();
        $this->ad(['status' => AdStatus::Live, 'published_at' => now()]);
        $this->assertSame(2, SocialPost::where('status', 'posted')->count());
    }

    public function test_failure_keeps_the_reason_without_the_key_and_never_breaks_the_ad(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::sequence()
                ->push(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)
                ->push(['ok' => true, 'result' => ['message_id' => 78]]),
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token '.self::FB_TOKEN]], 400),
        ]);
        $ad = $this->ad();
        Queue::fake();
        $this->goLive($ad); // saving the ad works even if networks fail
        $this->assertSame(AdStatus::Live, $ad->fresh()?->status);

        foreach (['telegram', 'facebook'] as $network) {
            try {
                (new PostAdToSocial($ad->id, $network))->handle();
                $this->fail('expected a failure');
            } catch (SocialPostFailed $e) {
                $this->assertStringNotContainsString('SECRET', $e->getMessage());
            }
        }
        $rows = SocialPost::orderBy('network')->get();
        $this->assertSame(['failed', 'failed'], $rows->pluck('status')->all());
        $this->assertStringContainsString('chat not found', (string) $rows->firstWhere('network', 'telegram')?->error);
        $this->assertStringNotContainsString('SECRET', $rows->pluck('error')->implode(' '));

        // Health page tells the owner.
        $check = collect(app(HealthChecker::class)->run())->firstWhere('key', 'social');
        $this->assertSame('warn', $check['status']);
        $this->assertStringContainsString('2 failed', $check['message']);

        // A later try that works.
        (new PostAdToSocial($ad->id, 'telegram'))->handle();
        $this->assertSame('posted', SocialPost::where('network', 'telegram')->value('status'));
        $this->assertSame(2, SocialPost::where('network', 'telegram')->value('attempts'));
    }

    public function test_connection_error_hides_the_token(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28 for https://api.telegram.org/bot'.self::TG_TOKEN.'/sendMessage'));
        config(['services.facebook.page_token' => null]);
        Queue::fake();
        $ad = $this->ad();
        $this->goLive($ad);

        $this->expectException(SocialPostFailed::class);
        try {
            (new PostAdToSocial($ad->id, 'telegram'))->handle();
        } finally {
            $this->assertStringNotContainsString('SECRET', (string) SocialPost::value('error'));
            $this->assertStringContainsString('***', (string) SocialPost::value('error'));
        }
    }

    public function test_skipped_when_things_changed_while_waiting(): void
    {
        $this->fakeOk();
        Queue::fake();
        $ad = $this->ad();
        $this->goLive($ad);

        $ad->forceFill(['status' => AdStatus::Rejected])->save(); // taken down before the queue ran
        (new PostAdToSocial($ad->id, 'telegram'))->handle();
        app(FeatureManager::class)->set('social.autopost', false);
        (new PostAdToSocial($ad->id, 'facebook'))->handle();

        Http::assertNothingSent();
        $this->assertSame(['skipped', 'skipped'], SocialPost::pluck('status')->all());
    }

    public function test_health_line_only_when_on(): void
    {
        config(['services.telegram.bot_token' => null, 'services.facebook.page_token' => null]);
        $check = collect(app(HealthChecker::class)->run())->firstWhere('key', 'social');
        $this->assertSame('warn', $check['status']);

        app(FeatureManager::class)->set('social.autopost', false);
        $this->assertNull(collect(app(HealthChecker::class)->run())->firstWhere('key', 'social'));
    }
}
