<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Category;
use App\Models\City;
use App\Models\Region;
use App\Support\Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_only_mogadishu_is_on_at_launch(): void
    {
        $this->assertSame(['mogadishu'], collect(app(Catalog::class)->cities())->pluck('slug')->all());
    }

    public function test_mogadishu_has_its_districts(): void
    {
        $mogadishu = City::where('slug', 'mogadishu')->first();

        $this->assertGreaterThanOrEqual(17, $mogadishu->areas()->count());
        $this->assertTrue($mogadishu->areas()->where('slug', 'hodan')->exists());
    }

    public function test_no_somaliland_cities(): void
    {
        $this->assertFalse(City::whereIn('slug', ['hargeisa', 'burao', 'berbera', 'borama'])->exists());
    }

    public function test_turning_a_city_on_shows_it_right_away(): void
    {
        City::where('slug', 'kismayo')->update(['enabled' => true]); // query update: no events
        app(Catalog::class)->flush();
        $this->assertContains('kismayo', collect(app(Catalog::class)->cities())->pluck('slug'));

        // Via the model (like the admin will do): cache is cleared automatically.
        City::where('slug', 'kismayo')->first()->update(['enabled' => false]);
        $this->assertNotContains('kismayo', collect(app(Catalog::class)->cities())->pluck('slug'));
    }

    public function test_region_off_hides_its_cities(): void
    {
        Region::where('slug', 'banadir')->first()->update(['enabled' => false]);

        $this->assertSame([], app(Catalog::class)->cities());
    }

    public function test_seeders_can_run_again_without_changing_switches(): void
    {
        City::where('slug', 'mogadishu')->first()->update(['enabled' => false]);
        Category::where('slug', 'jobs')->first()->update(['enabled' => false]);

        $this->seed();

        $this->assertFalse(City::where('slug', 'mogadishu')->value('enabled'));
        $this->assertFalse((bool) Category::where('slug', 'jobs')->value('enabled'));
        $this->assertSame(1, City::where('slug', 'mogadishu')->count());
    }

    public function test_categories_include_products_services_and_jobs(): void
    {
        $slugs = collect(app(Catalog::class)->categories())->pluck('slug');

        foreach (['vehicles', 'property', 'phones-tablets', 'livestock', 'services', 'jobs'] as $slug) {
            $this->assertContains($slug, $slugs);
        }
    }

    public function test_cars_have_the_fields_from_the_brief(): void
    {
        $fields = Category::where('slug', 'cars')->first()->allFields()->pluck('key')->all();

        $this->assertSame(['brand', 'model', 'year', 'mileage', 'fuel', 'transmission', 'colour'], $fields); // colour: PR 52
    }

    public function test_houses_land_and_phones_have_their_fields(): void
    {
        $this->assertSame(['bedrooms', 'bathrooms', 'size', 'furnished'], Category::where('slug', 'houses-for-rent')->first()->allFields()->pluck('key')->all());
        $this->assertSame(['size'], Category::where('slug', 'land')->first()->allFields()->pluck('key')->all());
        $this->assertSame(['brand', 'storage'], Category::where('slug', 'mobile-phones')->first()->allFields()->pluck('key')->all());
    }

    public function test_select_field_options_have_labels(): void
    {
        $brand = Category::where('slug', 'cars')->first()->fields()->where('key', 'brand')->first();

        $this->assertSame('Toyota', $brand->optionList()['toyota']);
    }

    public function test_jobs_and_services_have_no_new_used_condition(): void
    {
        $this->assertFalse(Category::where('slug', 'job-vacancies')->value('has_condition'));
        $this->assertFalse(Category::where('slug', 'cleaning')->value('has_condition'));
        $this->assertTrue((bool) Category::where('slug', 'cars')->value('has_condition'));
    }

    public function test_disabled_category_and_its_children_are_hidden(): void
    {
        Category::where('slug', 'jobs')->first()->update(['enabled' => false]);
        Category::where('slug', 'camels')->first()->update(['enabled' => false]);

        $tree = collect(app(Catalog::class)->categories());
        $this->assertNotContains('jobs', $tree->pluck('slug'));
        $this->assertNotContains('camels', $tree->firstWhere('slug', 'livestock')['children'] ? collect($tree->firstWhere('slug', 'livestock')['children'])->pluck('slug') : []);
        $this->assertFalse(Category::where('slug', 'job-vacancies')->first()->isLive());
    }

    public function test_lists_are_cached(): void
    {
        app(Catalog::class)->categories();
        app(Catalog::class)->cities();

        DB::enableQueryLog();
        app(Catalog::class)->categories();
        app(Catalog::class)->cities();

        // Ignore the cache table itself (shared hosting stores the cache in the database).
        $this->assertCount(0, collect(DB::getQueryLog())->reject(fn ($q) => str_contains($q['query'], '`cache`')));
    }

    public function test_names_follow_the_current_language_with_english_fallback(): void
    {
        $area = Area::first();
        $area->update(['name' => ['en' => 'Hodan', 'so' => 'Xodan']]);

        app()->setLocale('so');
        $this->assertSame('Xodan', $area->fresh()->label);

        app()->setLocale('en');
        $this->assertSame('Hodan', $area->fresh()->label);

        // Starting catalogue has Somali names; a missing one falls back to English.
        app()->setLocale('so');
        $this->assertSame('Baabuur', Category::where('slug', 'cars')->first()->label);
        Category::where('slug', 'cars')->first()->update(['name' => ['en' => 'Cars']]);
        $this->assertSame('Cars', Category::where('slug', 'cars')->first()->label);
    }

    public function test_seeding_again_never_overwrites_admin_names(): void
    {
        $cars = Category::where('slug', 'cars')->first();
        $cars->update(['name' => ['en' => 'Cars & SUVs']]);
        $city = City::where('slug', 'mogadishu')->first();
        $city->update(['name' => ['en' => 'Mogadishu city', 'so' => 'Xamar']]);

        $this->seed();

        $this->assertSame(['en' => 'Cars & SUVs', 'so' => 'Baabuur'], $cars->fresh()->name); // only the missing Somali was added
        $this->assertSame(['en' => 'Mogadishu city', 'so' => 'Xamar'], $city->fresh()->name);
    }
}
