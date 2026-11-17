<?php

namespace Tests\Feature;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Ads\AdStats;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdStatsTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36';

    private User $seller;

    private Ad $ad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->seller = User::factory()->completed()->create(['phone' => '615123456']);
        $this->ad = Ad::factory()->live()->create([
            'user_id' => $this->seller->id,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);
    }

    private function on(): void
    {
        app(FeatureManager::class)->set('ad_stats', true);
    }

    private function beacon(string $event, string $agent = self::BROWSER): TestResponse
    {
        return $this->withHeader('User-Agent', $agent)->post("/ads/{$this->ad->id}/event", ['e' => $event]);
    }

    /** @return array<string, int> */
    private function today(): array
    {
        $row = DB::table('ad_stats')->where('ad_id', $this->ad->id)->first();

        return $row ? ['views' => (int) $row->views, 'phone' => (int) $row->phone, 'whatsapp' => (int) $row->whatsapp, 'call' => (int) $row->call] : [];
    }

    public function test_off_by_default_nothing_counted_or_shown(): void
    {
        $this->assertFalse(feature('ad_stats'));
        $this->beacon('views')->assertNotFound();
        $this->get($this->ad->url())->assertOk()->assertDontSee('data-stats', false);
        $this->actingAs($this->seller)->get('/account/ads')->assertOk()->assertDontSee('/stats"', false);
        $this->actingAs($this->seller)->get("/account/ads/{$this->ad->id}/stats")->assertNotFound();
        $this->assertSame(0, DB::table('ad_stats')->count());
    }

    public function test_guest_views_and_taps_are_counted_without_cookies_or_token(): void
    {
        $this->on();
        $page = $this->get($this->ad->url())->assertOk()->assertSee('data-stats="'.url("/ads/{$this->ad->id}/event").'"', false);
        $this->assertSame([], $page->headers->getCookies());

        $sessions = DB::table('sessions')->count();
        $res = $this->beacon('views')->assertNoContent();
        $this->assertSame([], $res->headers->getCookies(), 'The beacon must not set cookies for guests.');
        $this->assertSame($sessions, DB::table('sessions')->count(), 'The beacon must not save a session.');
        $this->beacon('views');
        $this->beacon('whatsapp');
        $this->beacon('call');

        $this->assertSame(['views' => 2, 'phone' => 0, 'whatsapp' => 1, 'call' => 1], $this->today());
        // Nothing about the visitor is stored: only these columns exist.
        $this->assertSame(['ad_id', 'day', 'views', 'phone', 'whatsapp', 'call'], array_keys((array) DB::table('ad_stats')->first()));
    }

    public function test_show_number_saves_no_session_and_sets_no_cookie_for_guests(): void
    {
        $sessions = DB::table('sessions')->count();

        $res = $this->withHeader('User-Agent', self::BROWSER)->getJson("/ads/{$this->ad->id}/contact")->assertOk()->assertJsonStructure(['display', 'call', 'whatsapp']);

        $this->assertSame([], $res->headers->getCookies());
        $this->assertSame($sessions, DB::table('sessions')->count());
    }

    public function test_show_number_is_counted_on_the_server(): void
    {
        $this->on();

        $this->withHeader('User-Agent', self::BROWSER)->getJson("/ads/{$this->ad->id}/contact")->assertOk();

        $this->assertSame(1, $this->today()['phone']);
    }

    public function test_bots_seller_and_hidden_ads_are_not_counted(): void
    {
        $this->on();

        $this->beacon('views', 'Mozilla/5.0 (compatible; Googlebot/2.1)');
        $this->beacon('views', 'Mozilla/5.0 HeadlessChrome/120');
        $this->withHeader('User-Agent', '')->post("/ads/{$this->ad->id}/event", ['e' => 'views']);
        // The seller's own ad page has no beacon at all.
        $this->actingAs($this->seller)->get($this->ad->url())->assertOk()->assertDontSee('data-stats', false);
        auth()->logout();

        // The seller's own "Show number" tap.
        $this->actingAs($this->seller)->get($this->ad->url())->assertSee(url("/ads/{$this->ad->id}/contact?mine=1"), false);
        auth()->logout();
        $this->withHeader('User-Agent', self::BROWSER)->getJson("/ads/{$this->ad->id}/contact?mine=1")->assertOk();

        $this->ad->forceFill(['status' => AdStatus::Sold])->save();
        $this->beacon('views');

        $this->assertSame(0, DB::table('ad_stats')->count());
    }

    public function test_bad_beacons_are_refused(): void
    {
        $this->on();

        $this->beacon('phone')->assertStatus(422); // only the server counts number reveals
        $this->beacon('likes')->assertStatus(422);
        $this->withHeader('User-Agent', self::BROWSER)->post('/ads/999999/event', ['e' => 'views'])->assertNotFound();
        $this->assertSame(0, DB::table('ad_stats')->count());
    }

    public function test_seller_sees_30_day_numbers_and_the_stats_page(): void
    {
        $this->on();
        DB::table('ad_stats')->insert([
            ['ad_id' => $this->ad->id, 'day' => now('Africa/Mogadishu')->toDateString(), 'views' => 12, 'phone' => 3, 'whatsapp' => 2, 'call' => 1],
            ['ad_id' => $this->ad->id, 'day' => now('Africa/Mogadishu')->subDays(10)->toDateString(), 'views' => 8, 'phone' => 0, 'whatsapp' => 0, 'call' => 0],
            ['ad_id' => $this->ad->id, 'day' => now('Africa/Mogadishu')->subDays(40)->toDateString(), 'views' => 500, 'phone' => 0, 'whatsapp' => 0, 'call' => 0],
        ]);

        $this->actingAs($this->seller)->get('/account/ads')->assertOk()
            ->assertSee('20 views')->assertSee('6 contacts')
            ->assertSee('href="'.url("/account/ads/{$this->ad->id}/stats").'"', false);

        $this->actingAs($this->seller)->get("/account/ads/{$this->ad->id}/stats")->assertOk()
            ->assertSee('Ad stats')->assertSeeInOrder(['Views', '20', 'Show number taps', '3', 'WhatsApp taps', '2', 'Call taps', '1']);
        $this->actingAs($this->seller)->get("/so/account/ads/{$this->ad->id}/stats")->assertOk()->assertSee('Tirakoobka xayeysiiska');
    }

    public function test_other_users_cannot_see_the_stats(): void
    {
        $this->on();

        $this->actingAs(User::factory()->completed()->create())->get("/account/ads/{$this->ad->id}/stats")->assertNotFound();
        auth()->logout();
        $this->get("/account/ads/{$this->ad->id}/stats")->assertRedirect(url('/login'));
    }

    public function test_daily_rows_fill_missing_days_with_zero(): void
    {
        DB::table('ad_stats')->insert(['ad_id' => $this->ad->id, 'day' => now('Africa/Mogadishu')->subDays(2)->toDateString(), 'views' => 5, 'phone' => 0, 'whatsapp' => 0, 'call' => 0]);

        $days = AdStats::daily($this->ad, 14);

        $this->assertCount(14, $days);
        $this->assertSame(5, $days[11]['views']);
        $this->assertSame(0, $days[13]['views']);
        $this->assertSame(now('Africa/Mogadishu')->toDateString(), $days[13]['day']->toDateString());
    }

    public function test_old_rows_are_pruned_every_night(): void
    {
        DB::table('ad_stats')->insert([
            ['ad_id' => $this->ad->id, 'day' => now()->subDays(AdStats::KEEP_DAYS + 5)->toDateString(), 'views' => 1, 'phone' => 0, 'whatsapp' => 0, 'call' => 0],
            ['ad_id' => $this->ad->id, 'day' => now()->subDays(3)->toDateString(), 'views' => 1, 'phone' => 0, 'whatsapp' => 0, 'call' => 0],
        ]);

        $this->assertSame(1, AdStats::prune());
        $this->assertSame(1, DB::table('ad_stats')->count());
        $this->assertTrue(collect(app(Schedule::class)->events())->contains(fn ($e) => $e->description === 'ad-stats-prune'));
    }

    public function test_deleting_an_ad_for_good_removes_its_stats(): void
    {
        $this->on();
        $this->beacon('views');

        $this->ad->forceDelete();

        $this->assertSame(0, DB::table('ad_stats')->count());
    }
}
