<?php

namespace Tests\Feature;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Support\Catalog;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function car(string $brand, array $attrs = []): Ad
    {
        return Ad::factory()->live()->create(array_merge([
            'title' => ucfirst($brand).' car for sale',
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
            'fields' => ['brand' => $brand],
        ], $attrs));
    }

    public function test_brand_page_lists_only_that_brand_with_good_title_and_breadcrumbs(): void
    {
        $this->car('toyota', ['title' => 'Toyota Vitz clean']);
        $this->car('nissan', ['title' => 'Nissan Note cheap']);

        $res = $this->get('/cars/mogadishu/toyota')->assertOk()
            ->assertSee('<title>Toyota Cars in Mogadishu · iibsom</title>', false)
            ->assertSee('Toyota Vitz clean')->assertDontSee('Nissan Note cheap')
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertDontSee('noindex', false);
        // Breadcrumb: Home › Vehicles › Cars › Mogadishu › Toyota (last one is the current page)
        $this->assertMatchesRegularExpression('#Vehicles</a>.*Cars</a>.*Mogadishu</a>.*<span aria-current="page">Toyota</span>#s', $res->getContent());
    }

    public function test_category_city_page_links_to_brands_with_ads(): void
    {
        $this->car('toyota');
        $this->car('toyota');
        $this->car('nissan');
        $this->car('nissan', ['status' => AdStatus::Pending]); // not live: not counted

        $html = $this->get('/cars/mogadishu')->assertOk()->getContent();
        $this->assertStringContainsString(url('/cars/mogadishu/toyota'), $html);
        $this->assertMatchesRegularExpression('#Toyota <span class="text-stone-500">2</span>.*Nissan <span class="text-stone-500">1</span>#s', $html);
        $this->assertStringNotContainsString(url('/cars/mogadishu/suzuki'), $html); // no ads: no link
    }

    public function test_unknown_brand_city_off_or_main_category_is_404(): void
    {
        $this->get('/cars/mogadishu/ferrari')->assertNotFound();
        $this->get('/cars/kismayo/toyota')->assertNotFound();      // city OFF
        $this->get('/vehicles/mogadishu/toyota')->assertNotFound(); // main category
        $this->get('/livestock/mogadishu/toyota')->assertNotFound();
    }

    public function test_ad_addresses_still_work_next_to_brand_pages(): void
    {
        $ad = $this->car('toyota');
        $this->get(parse_url($ad->url(), PHP_URL_PATH))->assertOk()->assertSee($ad->title);
    }

    public function test_empty_and_filtered_pages_are_not_for_google(): void
    {
        $this->get('/cars/mogadishu/suzuki')->assertOk()->assertSee('noindex', false);  // 0 ads
        $this->car('toyota');
        $this->get('/cars/mogadishu/toyota?condition=used')->assertSee('noindex', false);
        $this->get('/cars/mogadishu/toyota?page=2')->assertSee('noindex', false);
    }

    public function test_somali_title(): void
    {
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->car('toyota');

        $this->get('/so/cars/mogadishu/toyota')->assertOk()->assertSee('<title>Baabuur Toyota gudaha Muqdisho · iibsom</title>', false);
    }

    public function test_other_city_links_only_when_two_cities_are_on(): void
    {
        $this->car('toyota');
        $this->get('/cars/mogadishu')->assertDontSee('Also in:');

        City::where('slug', 'kismayo')->update(['enabled' => true]);
        app(Catalog::class)->flush();
        $this->get('/cars/mogadishu')->assertSee('Also in:')->assertSee(url('/cars/kismayo'), false);
    }

    public function test_sitemap_lists_landing_pages_with_ads(): void
    {
        $this->car('toyota');
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.url('/cars/mogadishu').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.url('/cars/mogadishu/toyota').'</loc>', $xml);
        $this->assertStringNotContainsString(url('/cars/mogadishu/nissan'), $xml);
    }

    public function test_switch_off(): void
    {
        $this->car('toyota');
        app(FeatureManager::class)->set('seo.landing_pages', false);

        $this->get('/cars/mogadishu/toyota')->assertNotFound();
        $this->get('/cars/mogadishu')->assertOk()->assertDontSee(url('/cars/mogadishu/toyota'), false);
    }
}
