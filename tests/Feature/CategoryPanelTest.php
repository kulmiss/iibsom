<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        foreach (['cars', 'cars', 'buses'] as $slug) {
            Ad::factory()->live()->create([
                'user_id' => User::factory()->completed()->create()->id,
                'category_id' => Category::where('slug', $slug)->value('id'),
                'city_id' => City::where('slug', 'mogadishu')->value('id'),
            ]);
        }
    }

    public function test_home_has_a_side_panel_with_sub_categories_and_counts(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();
        $vehicles = Category::where('slug', 'vehicles')->firstOrFail();

        $this->assertStringContainsString('id="cat-panel-'.$vehicles->id.'"', $html);
        $this->assertStringContainsString('aria-controls="cat-panel-'.$vehicles->id.'"', $html);
        $this->assertMatchesRegularExpression('#cat-panel-'.$vehicles->id.'".*?All in Vehicles \(3\).*?href="'.preg_quote(url('/cars'), '#').'".*?Cars.*?2 ads.*?Buses &amp; Minibuses.*?1 ad#s', $html);
        // Empty sub categories show while the switch is OFF…
        $this->assertStringContainsString(url('/boats'), $html);
    }

    public function test_hide_empty_switch(): void
    {
        $this->assertFalse(config('features')['catalog.hide_empty']['default']);
        app(FeatureManager::class)->set('catalog.hide_empty', true);

        $this->get('/')->assertOk()->assertSee(url('/cars'), false)->assertDontSee(url('/boats'), false);
        $this->get('/vehicles')->assertOk()->assertSee(url('/buses'), false)->assertDontSee(url('/boats'), false);
        $this->get('/boats')->assertOk()->assertSee('aria-current="page"', false); // the page itself still works, and shows itself in the filter tree
        $this->get('/cars')->assertOk()->assertDontSee(url('/boats'), false); // filter sidebar too
        $this->actingAs(User::factory()->completed()->create())->get('/post')->assertOk()->assertSee(url('/post/boats'), false); // posting offers all
    }

    public function test_main_category_page_starts_with_sub_category_tiles(): void
    {
        $this->get('/vehicles')->assertOk()
            ->assertSee('aria-label="Sub categories of Vehicles"', false)
            ->assertSeeInOrder(['Cars', 'Motorcycles', 'Trucks', 'Buses &amp; Minibuses', 'Vehicle Parts &amp; Accessories', 'Boats'], false)
            ->assertSee('3 results for Vehicles in Somalia');
    }

    public function test_sub_category_page_has_sibling_chips_breadcrumbs_and_result_line(): void
    {
        $html = (string) $this->get('/cars')->assertOk()
            ->assertSee('2 results for Cars in Somalia')
            ->assertSee('All in Vehicles')
            ->getContent();
        $this->assertMatchesRegularExpression('#href="'.preg_quote(url('/cars'), '#').'"[^>]*aria-current="page"#', $html);
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(url('/'), '#').'"[^>]*>iibsom</a>.*Vehicles</a>.*<span aria-current="page">Cars</span>#s', $html);

        $this->get('/cars/mogadishu')->assertOk()->assertSee('2 results for Cars in Mogadishu');
        $this->get('/so/cars')->assertOk()->assertSee('2 natiijo oo Baabuur ah gudaha Soomaaliya')->assertSee('Dhammaan Gaadiid');
        $this->get('/search?q=toyota')->assertOk()->assertSee('No results for “toyota” in Somalia');
    }
}
