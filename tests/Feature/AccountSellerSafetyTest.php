<?php

namespace Tests\Feature;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Cache\PageCache;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** PR 79: My account dashboard, a richer seller page, and the "How we keep you safe" article. */
class AccountSellerSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->seller = User::factory()->completed()->create(['name' => 'Sahra Seller', 'city_id' => City::where('slug', 'mogadishu')->value('id')]);
    }

    /** @param array<string, mixed> $attributes */
    private function ad(string $category, array $attributes = []): Ad
    {
        return Ad::factory()->live()->create($attributes + [
            'user_id' => $this->seller->id, 'category_id' => Category::where('slug', $category)->value('id'),
            'city_id' => $this->seller->city_id, 'published_at' => now(),
        ]);
    }

    private function switch(string $key, bool $on): void
    {
        app(FeatureManager::class)->set($key, $on);
        PageCache::bump();
    }

    // ---- Dashboard ----

    public function test_dashboard_shows_only_my_own_numbers(): void
    {
        foreach (['ad_stats', 'favourites', 'chat'] as $s) {
            $this->switch($s, true);
        }
        $live = $this->ad('cars');
        $this->ad('cars', ['status' => AdStatus::Pending, 'published_at' => null]);
        DB::table('ad_stats')->insert(['ad_id' => $live->id, 'day' => now('Africa/Mogadishu')->toDateString(), 'views' => 42, 'phone' => 3, 'whatsapp' => 2, 'call' => 1]);
        // Someone else's ad and views are not counted.
        $other = Ad::factory()->live()->create();
        DB::table('ad_stats')->insert(['ad_id' => $other->id, 'day' => now('Africa/Mogadishu')->toDateString(), 'views' => 999, 'phone' => 9, 'whatsapp' => 9, 'call' => 9]);

        $html = $this->actingAs($this->seller)->get('/account')->assertOk()->getContent();
        $start = (int) strpos($html, 'data-dashboard');
        $dash = substr($html, $start, (int) strpos($html, '</ul>', $start) - $start);

        $this->assertMatchesRegularExpression('/Live ads<\/span>\s*<span[^>]*>1</', $dash);
        $this->assertMatchesRegularExpression('/Waiting for a check<\/span>\s*<span[^>]*>1</', $dash);
        $this->assertMatchesRegularExpression('/Views \(30 days\)<\/span>\s*<span[^>]*>42</', $dash);
        $this->assertMatchesRegularExpression('/Calls and WhatsApp \(30 days\)<\/span>\s*<span[^>]*>6</', $dash);
        $this->assertStringNotContainsString('999', $dash);
        $this->assertStringContainsString('data-dashboard-actions', $html);

        $this->actingAs($this->seller)->get('/so/account')->assertSee(__('account.dash.live', [], 'so'));
    }

    public function test_dashboard_switch_off_keeps_the_plain_menu(): void
    {
        $this->switch('account.dashboard', false);
        $this->actingAs($this->seller)->get('/account')->assertOk()->assertDontSee('data-dashboard', false)->assertSee('max-w-md', false);
    }

    // ---- Seller page ----

    public function test_seller_page_filters_by_category_and_sorts(): void
    {
        $this->ad('cars', ['title' => 'Cheap Vitz', 'price' => 3000]);
        $this->ad('cars', ['title' => 'Dear Hilux', 'price' => 30000]);
        $this->ad('mobile-phones', ['title' => 'Samsung A10', 'price' => 90]);

        $html = $this->get('/seller/'.$this->seller->id)->assertOk()->getContent();
        $this->assertStringContainsString('data-seller-cover', $html);
        $this->assertStringContainsString('data-seller-facets', $html);
        $this->assertStringContainsString('3 ads', $html);

        $cars = $this->get('/seller/'.$this->seller->id.'?c=cars&sort=cheapest')->assertOk()->getContent();
        $this->assertStringNotContainsString('Samsung A10', $cars);
        $this->assertLessThan(strpos($cars, 'Dear Hilux'), strpos($cars, 'Cheap Vitz'));
        $this->assertStringContainsString('3 ads', $cars); // the header still counts all of them

        $dear = $this->get('/seller/'.$this->seller->id.'?sort=expensive')->getContent();
        $this->assertLessThan(strpos($dear, 'Cheap Vitz'), strpos($dear, 'Dear Hilux'));

        $this->get('/seller/'.$this->seller->id.'?c=not-a-category')->assertOk()->assertSee('Samsung A10');
        $this->get('/seller/'.$this->seller->id.'?sort=random')->assertRedirect();
    }

    public function test_one_category_needs_no_filter_chips_and_switch_off_is_the_old_page(): void
    {
        $this->ad('cars', ['title' => 'Only car']);
        $this->assertStringNotContainsString('data-seller-facets', $this->get('/seller/'.$this->seller->id)->getContent());

        $this->ad('mobile-phones', ['title' => 'Phone']);
        $this->switch('sellers.page_plus', false);
        $html = $this->get('/seller/'.$this->seller->id.'?c=cars')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-seller-cover', $html);
        $this->assertStringContainsString('Phone', $html); // the filter is ignored when OFF
    }

    public function test_seller_page_stays_cached_for_guests(): void
    {
        $this->ad('cars');
        $response = $this->get('/seller/'.$this->seller->id)->assertOk();
        $this->assertSame([], $response->headers->getCookies());
    }

    // ---- How we keep you safe ----

    public function test_safety_article_in_both_languages_and_linked(): void
    {
        $this->get('/help/how-we-keep-you-safe')->assertOk()->assertSee('How we keep you safe')
            ->assertSee('People check every ad.')->assertSee('Never send money first');
        $this->get('/so/help/how-we-keep-you-safe')->assertOk()->assertSee('Sida aan kuu ilaalino');

        $home = $this->get('/')->getContent();
        $this->assertStringContainsString(lroute('help.show', ['article' => 'how-we-keep-you-safe']), $home);
    }

    public function test_safety_article_only_claims_what_is_true(): void
    {
        $this->switch('ads.trusted_sellers', true);
        $this->get('/help/how-we-keep-you-safe')->assertOk()->assertDontSee('People check every ad.')->assertSee('People check ads.');
    }

    public function test_safety_article_switch_off(): void
    {
        $this->switch('help.how_we_keep_safe', false);
        $this->get('/help/how-we-keep-you-safe')->assertNotFound();
        $this->assertStringNotContainsString('how-we-keep-you-safe', $this->get('/')->getContent());
    }
}
