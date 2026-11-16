<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Support\Features\FeatureManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrowseTest extends TestCase
{
    use RefreshDatabase;

    private Category $cars;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->cars = Category::where('slug', 'cars')->first();
    }

    private function car(array $attrs = []): Ad
    {
        return Ad::factory()->live()->create(['category_id' => $this->cars->id] + $attrs);
    }

    public function test_category_page_lists_live_ads_only(): void
    {
        $this->car(['title' => 'Toyota Hilux clean']);
        Ad::factory()->create(['category_id' => $this->cars->id, 'title' => 'Waiting Nissan']); // pending

        $this->get('/cars')->assertOk()->assertSee('Toyota Hilux clean')->assertDontSee('Waiting Nissan')->assertSee('1 ad');
    }

    public function test_main_category_shows_all_its_sub_categories(): void
    {
        $this->car(['title' => 'A car']);
        Ad::factory()->live()->create(['category_id' => Category::where('slug', 'trucks-buses')->value('id'), 'title' => 'A truck']);

        $this->get('/vehicles')->assertOk()->assertSee('A car')->assertSee('A truck');
    }

    public function test_unknown_or_off_category_is_404(): void
    {
        $this->get('/not-a-category')->assertNotFound();
        $this->cars->update(['enabled' => false]);
        $this->get('/cars')->assertNotFound();
    }

    public function test_city_page_needs_a_live_city(): void
    {
        $this->get('/cars/mogadishu')->assertOk();
        $this->get('/cars/kismayo')->assertNotFound(); // OFF
    }

    public function test_keyword_search(): void
    {
        $this->car(['title' => 'Toyota Land Cruiser V8']);
        $this->car(['title' => 'Suzuki Swift small']);

        $this->get('/search?q=cruiser')->assertOk()->assertSee('Toyota Land Cruiser V8')->assertDontSee('Suzuki Swift');
        $this->get('/search?q=to')->assertOk(); // short words still work
    }

    public function test_price_condition_and_sort(): void
    {
        $this->car(['title' => 'Cheap one', 'price' => 1000, 'condition' => 'used']);
        $this->car(['title' => 'Dear one', 'price' => 50000, 'condition' => 'new']);

        $this->get('/cars?min=2000')->assertDontSee('Cheap one')->assertSee('Dear one');
        $this->get('/cars?condition=used')->assertSee('Cheap one')->assertDontSee('Dear one');
        $this->get('/cars?sort=cheapest')->assertSeeInOrder(['Cheap one', 'Dear one']);
        $this->get('/cars?sort=expensive')->assertSeeInOrder(['Dear one', 'Cheap one']);
    }

    public function test_custom_field_filters(): void
    {
        $this->car(['title' => 'Toyota 2015', 'fields' => ['brand' => 'toyota', 'year' => 2015]]);
        $this->car(['title' => 'Nissan 2008', 'fields' => ['brand' => 'nissan', 'year' => 2008]]);

        $this->get('/cars?f[brand]=toyota')->assertSee('Toyota 2015')->assertDontSee('Nissan 2008');
        $this->get('/cars?f[year][min]=2010')->assertSee('Toyota 2015')->assertDontSee('Nissan 2008');
        $this->get('/cars?f[hack]=x')->assertOk(); // unknown filters are ignored
        $this->get('/cars')->assertSee('name="f[brand]"', false);
    }

    public function test_bad_input_is_refused_safely(): void
    {
        $this->get('/search?sort=drop')->assertRedirect();
        $this->get('/search?q='.urlencode('"<script>'))->assertOk()->assertDontSee('"<script>', false);
    }

    public function test_demo_ads_hidden_unless_switched_on(): void
    {
        $demo = $this->car(['title' => 'Demo Toyota']);
        $demo->forceFill(['is_demo' => true])->save();

        $this->get('/cars')->assertDontSee('Demo Toyota');
        app(FeatureManager::class)->set('site.demo_data', true);
        $this->get('/cars')->assertSee('Demo Toyota');
    }

    public function test_expired_ads_are_not_listed(): void
    {
        $this->car(['title' => 'Old car', 'expires_at' => now()->subDay()]);

        $this->get('/cars')->assertDontSee('Old car');
    }

    public function test_home_shows_latest_ads_and_search_form_works(): void
    {
        $this->car(['title' => 'Fresh Toyota']);

        $this->get('/')->assertSee('Fresh Toyota')->assertSee('action="'.url('/search').'"', false);
    }

    public function test_filtered_pages_are_not_indexed(): void
    {
        $this->get('/cars?min=10')->assertSee('noindex, follow', false);
        $this->get('/cars')->assertDontSee('noindex', false);
    }

    public function test_listing_query_budget(): void
    {
        foreach (range(1, 10) as $i) {
            $this->car(['title' => "Car {$i}"]);
        }
        $this->get('/cars'); // warm caches

        \DB::enableQueryLog();
        $this->get('/cars')->assertOk();
        $queries = collect(\DB::getQueryLog())->reject(fn ($q) => str_contains($q['query'], '`cache`'));

        $this->assertLessThanOrEqual(12, $queries->count(), $queries->pluck('query')->implode("\n"));
    }

    public function test_ad_without_photo_shows_its_category_icon(): void
    {
        Ad::factory()->live()->create([
            'title' => 'Car without photo',
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);

        $carIcon = (string) icon('car', 'size-10');
        $this->get('/cars')->assertOk()->assertSee($carIcon, false);
        $this->get('/search?q=without')->assertOk()->assertSee($carIcon, false);
    }
}
