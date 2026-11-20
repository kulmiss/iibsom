<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Area;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Cache\PageCache;
use App\Support\Features\FeatureManager;
use App\Support\Home\HomeSections;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** PR 75: a rich home page with real data only; every part has its own switch and hides when empty. */
class HomeRichTest extends TestCase
{
    use RefreshDatabase;

    private City $mogadishu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        app(FeatureManager::class)->set('login.google', true);
        $this->mogadishu = City::where('slug', 'mogadishu')->firstOrFail();
    }

    private function switch(string $key, bool $on): void
    {
        app(FeatureManager::class)->set($key, $on);
        PageCache::bump();
    }

    /** @param array<string, mixed> $attributes */
    private function ad(array $attributes = []): Ad
    {
        return Ad::factory()->live()->create($attributes + [
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => $this->mogadishu->id,
            'published_at' => now(),
        ]);
    }

    // ---- Hero ----

    public function test_hero_has_a_promise_and_a_free_post_button_in_both_languages(): void
    {
        $this->get('/')->assertOk()->assertSee('data-home-promise', false)
            ->assertSee('Buy and sell anything in Somalia. Free, fast and safe.')->assertSee('Post an ad free');
        $this->get('/so')->assertOk()->assertSee(__('site.home.promise', [], 'so'))->assertSee(__('site.home.post_free', [], 'so'));
    }

    public function test_popular_searches_come_from_ad_title_words_without_numbers_places_or_empty_words(): void
    {
        DB::table('search_terms')->insert([
            ['term' => 'goats', 'ads' => 16], ['term' => 'young goats', 'ads' => 3], ['term' => 'samsung', 'ads' => 5],
            ['term' => 'samsung galaxy', 'ads' => 3], ['term' => '128 gb', 'ads' => 9], ['term' => 'hodan', 'ads' => 12],
            ['term' => 'healthy', 'ads' => 8], ['term' => 'toyota', 'ads' => 2], ['term' => 'land', 'ads' => 4],
        ]);

        $terms = HomeSections::popularSearches();
        $this->assertSame(['goats', 'samsung galaxy', 'land'], $terms);

        $page = $this->get('/')->assertOk();
        $page->assertSee('data-popular-searches', false)->assertSee('href="'.e(lroute('search', ['q' => 'samsung galaxy'])).'"', false);
    }

    public function test_what_visitors_type_never_becomes_a_popular_search(): void
    {
        $this->get('/search?q=secretword');
        $this->get('/search-suggest?q=secretword');

        $this->assertSame([], HomeSections::popularSearches());
        $this->get('/')->assertDontSee('secretword')->assertDontSee('data-popular-searches', false);
    }

    public function test_hero_switches_off_show_the_old_hero(): void
    {
        DB::table('search_terms')->insert(['term' => 'goats', 'ads' => 16]);
        foreach (['home.promise', 'home.popular_searches', 'home.post_button'] as $key) { // were "home.hero_plus" (PR 86)
            $this->switch($key, false);
        }

        $this->get('/')->assertOk()->assertDontSee('data-home-promise', false)->assertDontSee('data-popular-searches', false)->assertDontSee('data-home-post', false);
    }

    // ---- Popular places ----

    public function test_popular_places_show_districts_with_counts_and_working_links(): void
    {
        $hodan = Area::where('city_id', $this->mogadishu->id)->orderBy('id')->firstOrFail();
        $other = Area::where('city_id', $this->mogadishu->id)->where('id', '!=', $hodan->id)->orderBy('id')->firstOrFail();
        $this->ad(['area_id' => $hodan->id]);
        $this->ad(['area_id' => $hodan->id]);
        $this->ad(['area_id' => $other->id]);

        $html = $this->get('/')->assertOk()->assertSee('Popular places')->getContent();
        $this->assertStringContainsString(e(lroute('search', ['city' => 'mogadishu', 'area' => $hodan->id])), $html);
        $this->assertStringContainsString('2 ads', $html);

        $this->get(lroute('search', ['city' => 'mogadishu', 'area' => $hodan->id]))->assertOk()->assertSee('2 results', false);
    }

    public function test_popular_places_hide_with_fewer_than_two_places_or_switch_off(): void
    {
        $area = Area::where('city_id', $this->mogadishu->id)->firstOrFail();
        $this->ad(['area_id' => $area->id]);
        $this->get('/')->assertDontSee('data-home-places', false);

        $this->ad(['area_id' => Area::where('city_id', $this->mogadishu->id)->where('id', '!=', $area->id)->value('id')]);
        $this->switch('home.places', true);
        $this->get('/')->assertSee('data-home-places', false);

        $this->switch('home.places', false);
        $this->get('/')->assertDontSee('data-home-places', false);
    }

    // ---- How it works ----

    public function test_how_it_works_buy_and_sell_in_three_steps(): void
    {
        $this->get('/')->assertOk()->assertSee('How iibsom works')->assertSee(['Find it', 'Contact the seller', 'Meet and check', 'Take good photos', 'Post your ad free', 'Answer buyers']);
        $this->get('/so')->assertSee(__('site.home.how_title', [], 'so'))->assertSee(__('site.home.buy_3', [], 'so'));

        $this->switch('home.how_it_works', false);
        $this->get('/')->assertDontSee('data-home-how', false);
    }

    // ---- Trust strip: true facts only ----

    public function test_trust_numbers_show_only_when_they_mean_something(): void
    {
        $this->ad();
        $this->get('/')->assertSee('data-home-trust', false)->assertDontSee('live ad')->assertSee('Posting an ad is free');

        $sellers = User::factory()->completed()->count(3)->create();
        foreach (range(1, 10) as $i) {
            $this->ad(['user_id' => $sellers[$i % 3]->id]);
        }
        PageCache::bump();
        $this->get('/')->assertSee('11 live ads')->assertSee('4 sellers');
    }

    public function test_checked_by_a_person_is_claimed_only_while_true(): void
    {
        $this->get('/')->assertSee('Every ad is checked by a person');

        $this->switch('ads.trusted_sellers', true);
        $this->get('/')->assertDontSee('Every ad is checked by a person');

        $this->switch('ads.trusted_sellers', false);
        $this->switch('ads.approval', false);
        $this->get('/')->assertDontSee('Every ad is checked by a person');
    }

    public function test_trust_strip_switch_off(): void
    {
        $this->switch('home.trust_strip', false);
        $this->get('/')->assertDontSee('data-home-trust', false);
    }

    // ---- Featured and trusted sellers (Phase 2 switches) ----

    public function test_featured_row_needs_boosts_and_a_running_boost(): void
    {
        $boosted = $this->ad(['title' => 'Boosted Toyota Vitz', 'boost_level' => 2, 'boosted_until' => now()->addDay()]);
        $this->ad(['title' => 'Old boost Mazda', 'boost_level' => 2, 'boosted_until' => now()->subDay()]);

        $this->get('/')->assertDontSee('data-home-featured', false);

        $this->switch('boosts', true);
        $html = $this->get('/')->assertSee('Featured ads')->getContent();
        $featured = substr($html, (int) strpos($html, 'data-home-featured'), 4000);
        $this->assertStringContainsString($boosted->title, $featured);
        $this->assertStringNotContainsString('Old boost Mazda', $featured);
    }

    public function test_verified_sellers_with_live_ads_only_never_banned(): void
    {
        $good = User::factory()->completed()->create(['name' => 'Hodan Verified', 'verified_at' => now()]);
        $banned = User::factory()->completed()->create(['name' => 'Banned Verified', 'verified_at' => now(), 'banned_at' => now()]);
        $noAds = User::factory()->completed()->create(['name' => 'Nothing Verified', 'verified_at' => now()]);
        $this->ad(['user_id' => $good->id]);
        $this->ad(['user_id' => $banned->id]);

        $this->switch('verified_badge', false); // ON by default since PR 106
        $this->get('/')->assertDontSee('data-home-trusted', false);

        $this->switch('verified_badge', true);
        $this->get('/')->assertSee('Shops and verified sellers')->assertSee('Hodan Verified')
            ->assertSee(lroute('seller.show', ['user' => $good->id]), false)
            ->assertDontSee('Banned Verified')->assertDontSee('Nothing Verified');
        $this->assertNotNull($noAds->id);
    }

    // ---- Install card ----

    public function test_install_card_is_in_the_page_hidden_until_the_phone_offers_it(): void
    {
        $this->get('/')->assertSee('data-install-card hidden', false)->assertSee('Add iibsom to your phone');
        $this->get('/so')->assertSee(__('site.home.install_title', [], 'so'));

        $this->switch('pwa.install_prompt', false);
        $this->get('/')->assertDontSee('data-install-card', false);
        $this->switch('pwa.install_prompt', true);
        $this->switch('pwa', false);
        $this->get('/')->assertDontSee('data-install-card', false);
    }

    // ---- Still fast and private ----

    public function test_guest_home_stays_cached_and_cookie_free(): void
    {
        $this->ad();
        $first = $this->get('/')->assertOk();
        $this->assertSame([], $first->headers->getCookies());
        $this->assertStringContainsString('public', (string) $first->headers->get('Cache-Control'));
    }
}
