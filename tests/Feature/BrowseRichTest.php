<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Area;
use App\Models\Category;
use App\Models\City;
use App\Support\Cache\PageCache;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** PR 77: quick filters on top, helpful "no results", a short true intro on category pages. */
class BrowseRichTest extends TestCase
{
    use RefreshDatabase;

    private City $mogadishu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->mogadishu = City::where('slug', 'mogadishu')->firstOrFail();
    }

    /** @param array<string, mixed> $attributes */
    private function car(string $title, ?int $price, array $attributes = []): Ad
    {
        return Ad::factory()->live()->create($attributes + [
            'title' => $title, 'price' => $price, 'condition' => 'used',
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => $this->mogadishu->id, 'published_at' => now(),
            'fields' => ['brand' => 'toyota', 'year' => 2012],
        ]);
    }

    private function switch(string $key, bool $on): void
    {
        app(FeatureManager::class)->set($key, $on);
        PageCache::bump();
    }

    private function part(string $html, string $marker, int $length = 6000): string
    {
        $start = strpos($html, $marker);
        $this->assertNotFalse($start, 'missing '.$marker);

        return substr($html, (int) $start, $length);
    }

    // ---- Quick filters ----

    public function test_sort_links_keep_the_other_filters(): void
    {
        $this->car('Toyota Vitz', 5000);
        $bar = $this->part($this->get('/cars?condition=used')->assertOk()->getContent(), 'data-quick-bar');

        $this->assertStringContainsString(e(url('/cars').'?condition=used&sort=cheapest'), $bar);
        $this->assertStringContainsString(e(url('/cars').'?condition=used&sort=expensive'), $bar);
        $this->get('/cars?condition=used&sort=cheapest')->assertOk()->assertSee('aria-current="true"', false);
    }

    public function test_price_box_keeps_every_other_filter_as_hidden_fields(): void
    {
        $this->car('Toyota Vitz', 5000);
        $bar = $this->part($this->get('/cars?condition=used&f[brand]=toyota&min=100')->assertOk()->getContent(), 'data-quick-bar');

        $this->assertStringContainsString('name="condition" value="used"', $bar);
        $this->assertStringContainsString('name="f[brand]" value="toyota"', $bar);
        $this->assertStringNotContainsString('type="hidden" name="min"', $bar); // typed again in the box
        $this->assertStringContainsString('name="min" type="number"', $bar);
    }

    public function test_condition_and_district_drop_downs(): void
    {
        $hodan = Area::where('city_id', $this->mogadishu->id)->orderBy('id')->firstOrFail();
        $this->car('Toyota Vitz', 5000, ['area_id' => $hodan->id]);

        $bar = $this->part($this->get('/cars/mogadishu')->assertOk()->getContent(), 'data-quick-bar', 12000);
        $this->assertStringContainsString(e(url('/cars/mogadishu').'?condition=new'), $bar);
        $this->assertStringContainsString(e(url('/cars/mogadishu').'?area='.$hodan->id), $bar);

        $this->get('/cars/mogadishu?area='.$hodan->id)->assertOk()->assertSee('1 result', false);
        $this->get('/so/cars/mogadishu')->assertOk()->assertSee(__('ads.browse.all_areas', [], 'so'));
    }

    public function test_quick_bar_switch_off_brings_back_the_sort_in_the_panel(): void
    {
        $html = $this->get('/cars')->getContent();
        $this->assertStringContainsString('data-quick-bar', $html);
        $this->assertStringContainsString('<div class="lg:hidden" data-panel-sort>', $html);

        $this->switch('search.quick_bar', false);
        $html = $this->get('/cars')->getContent();
        $this->assertStringNotContainsString('data-quick-bar', $html);
        $this->assertStringContainsString('<div class="" data-panel-sort>', $html);
    }

    public function test_no_second_search_box_in_the_panel_while_the_header_has_one(): void
    {
        $this->assertStringNotContainsString('id="fq"', $this->get('/cars')->getContent());
        $this->assertStringContainsString('type="hidden" name="q"', $this->get('/cars?q=vitz')->getContent());

        $this->switch('site.header_search', false);
        $this->assertStringContainsString('id="fq"', $this->get('/cars')->getContent());
    }

    // ---- Intro ----

    public function test_intro_says_true_numbers_in_both_languages(): void
    {
        $this->car('Toyota Vitz', 4100);
        $this->car('Toyota Hilux', 28500);
        $this->car('Price on request', null);

        $intro = $this->part($this->get('/cars')->assertOk()->getContent(), 'data-intro', 600);
        $this->assertStringContainsString('3 ads right now', $intro);
        $this->assertStringContainsString('Prices from $4,100 to $28,500.', $intro);

        $this->get('/so/cars')->assertSee(__('ads.browse.intro_price', ['min' => '$4,100', 'max' => '$28,500'], 'so'));
    }

    public function test_intro_only_on_the_plain_first_page_of_a_category(): void
    {
        $this->car('Toyota Vitz', 4100);

        $this->assertStringNotContainsString('data-intro', $this->get('/cars?condition=used')->getContent());
        $this->assertStringNotContainsString('data-intro', $this->get('/search?q=toyota')->getContent());
        $this->assertStringNotContainsString('data-intro', $this->get('/trucks')->getContent()); // no ads
        $this->assertStringContainsString('Price: $4,100.', $this->get('/cars')->getContent());

        $this->switch('seo.intro', false);
        $this->assertStringNotContainsString('data-intro', $this->get('/cars')->getContent());
    }

    // ---- No results ----

    public function test_no_results_offers_to_drop_one_filter_and_shows_the_newest_of_the_category(): void
    {
        $this->car('Toyota Vitz cheap', 4100);
        Ad::factory()->live()->create(['title' => 'Samsung phone', 'category_id' => Category::where('slug', 'mobile-phones')->value('id'), 'city_id' => $this->mogadishu->id]);

        $html = $this->get('/cars?condition=new&min=90000')->assertOk()->getContent();
        $try = $this->part($html, 'data-try-without', 3000);
        $this->assertStringContainsString(e(url('/cars').'?min=90000'), $try);      // drops "New"
        $this->assertStringContainsString(e(url('/cars').'?condition=new'), $try);  // drops the price

        $fallback = $this->part($html, 'data-fallback');
        $this->assertStringContainsString('Latest in Cars', $fallback);
        $this->assertStringContainsString('Toyota Vitz cheap', $fallback);
        $this->assertStringNotContainsString('Samsung phone', $fallback);
    }

    public function test_rich_no_results_switch_off(): void
    {
        $this->car('Toyota Vitz cheap', 4100);
        $this->switch('search.rich_empty', false);

        $html = $this->get('/cars?condition=new&min=90000')->getContent();
        $this->assertStringNotContainsString('data-try-without', $html);
        $this->assertStringNotContainsString('data-fallback', $html);
        $this->assertStringContainsString('data-empty-state', $html);
    }

    public function test_category_page_stays_cached_and_cookie_free(): void
    {
        $this->car('Toyota Vitz', 4100);
        $response = $this->get('/cars')->assertOk();
        $this->assertSame([], $response->headers->getCookies());
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
    }
}
