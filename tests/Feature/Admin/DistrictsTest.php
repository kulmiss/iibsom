<?php

namespace Tests\Feature\Admin;

use App\Models\Ad;
use App\Models\AdminActivity;
use App\Models\Area;
use App\Models\Category;
use App\Models\City;
use App\Support\Features\FeatureManager;
use Database\Seeders\LocationSeeder;

/** PR 111: districts under cities — starting list, filters, and the Super Admin's tools. */
class DistrictsTest extends AdminTestCase
{
    private City $mogadishu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mogadishu = City::where('slug', 'mogadishu')->firstOrFail();
    }

    public function test_starting_list_has_mogadishu_and_hargeisa_marked_for_review_and_new_cities_are_off(): void
    {
        $hargeisa = City::where('slug', 'hargeisa')->firstOrFail();
        $this->assertFalse($hargeisa->enabled);
        $this->assertSame(['26 June', 'Ahmed Dhagah', 'Gacan Libah', 'Ibrahim Koodbuur', 'Mohamed Haybe', 'Mohamed Mooge'], $hargeisa->areas->map(fn (Area $a) => $a->name['en'])->all());
        $this->assertSame('Hargeysa', $hargeisa->name['so']);
        $this->assertTrue($hargeisa->areas->every(fn (Area $a) => $a->needs_review));
        $this->assertGreaterThanOrEqual(18, $this->mogadishu->areas()->count());
        foreach (['burao', 'berbera', 'borama'] as $slug) {
            $this->assertFalse((bool) City::where('slug', $slug)->value('enabled'), $slug);
        }
    }

    public function test_adding_missing_places_changes_nothing_that_exists(): void
    {
        $hodan = Area::where('slug', 'hodan')->firstOrFail();
        $hodan->forceFill(['name' => ['en' => 'Hodan (KM4)', 'so' => 'Hodan'], 'needs_review' => false, 'enabled' => false, 'sort' => 99])->save();
        City::where('slug', 'hargeisa')->update(['enabled' => true]);
        Area::where('slug', 'mohamed-mooge')->delete();

        (new LocationSeeder)->addMissing();
        (new LocationSeeder)->addMissing();

        $hodan->refresh();
        $this->assertSame('Hodan (KM4)', $hodan->name['en']);
        $this->assertFalse($hodan->needs_review);
        $this->assertFalse($hodan->enabled);
        $this->assertSame(99, $hodan->sort);
        $this->assertTrue((bool) City::where('slug', 'hargeisa')->value('enabled'));
        $this->assertSame(1, Area::where('slug', 'mohamed-mooge')->count()); // the missing one came back once
        $this->assertSame(1, City::where('slug', 'hargeisa')->count());
    }

    public function test_district_filter_shows_on_property_and_vehicles_and_everywhere_with_the_switch(): void
    {
        $this->get('/cars/mogadishu')->assertOk()->assertSee('id="farea"', false)->assertSee('All districts');
        $this->get('/property/mogadishu')->assertOk()->assertSee('id="farea"', false);
        $this->get('/phones-tablets/mogadishu')->assertOk()->assertDontSee('id="farea"', false);
        $this->get('/search?city=mogadishu&q=phone')->assertOk()->assertDontSee('id="farea"', false);
        $this->get('/cars')->assertOk()->assertDontSee('id="farea"', false); // a city first

        app(FeatureManager::class)->set('search.districts_all', true);
        $this->get('/phones-tablets/mogadishu')->assertOk()->assertSee('id="farea"', false);
        $this->get('/search?city=mogadishu&q=phone')->assertOk()->assertSee('id="farea"', false);
    }

    public function test_filter_by_district_works_with_the_city(): void
    {
        $hodan = Area::where('slug', 'hodan')->firstOrFail();
        $cars = Category::where('slug', 'cars')->value('id');
        Ad::factory()->live()->create(['title' => 'Car in Hodan', 'category_id' => $cars, 'city_id' => $this->mogadishu->id, 'area_id' => $hodan->id]);
        Ad::factory()->live()->create(['title' => 'Car in Wadajir', 'category_id' => $cars, 'city_id' => $this->mogadishu->id, 'area_id' => Area::where('slug', 'wadajir')->value('id')]);

        $this->get('/cars/mogadishu?area='.$hodan->id)->assertOk()->assertSee('Car in Hodan')->assertDontSee('Car in Wadajir');
        // A district chosen elsewhere (home "Popular places") still filters a list without the drop-down, and stays chosen.
        $phones = Category::where('slug', 'mobile-phones')->value('id') ?? Category::where('slug', 'phones-tablets')->value('id');
        Ad::factory()->live()->create(['title' => 'Phone in Hodan', 'category_id' => $phones, 'city_id' => $this->mogadishu->id, 'area_id' => $hodan->id]);
        Ad::factory()->live()->create(['title' => 'Phone in Wadajir', 'category_id' => $phones, 'city_id' => $this->mogadishu->id, 'area_id' => Area::where('slug', 'wadajir')->value('id')]);
        $this->get('/search?city=mogadishu&area='.$hodan->id)->assertOk()->assertSee('Phone in Hodan')->assertDontSee('Phone in Wadajir')
            ->assertSee('<input type="hidden" name="area" value="'.$hodan->id.'">', false)->assertDontSee('id="farea"', false);

        // A district of another city is ignored.
        $hargeisaArea = Area::where('slug', '26-june')->value('id');
        $this->get('/cars/mogadishu?area='.$hargeisaArea)->assertOk()->assertSee('Car in Hodan')->assertSee('Car in Wadajir');
    }

    public function test_super_admin_adds_edits_orders_reviews_and_deletes(): void
    {
        $this->asStaff();
        $new = $this->postJson("/admin/api/cities/{$this->mogadishu->id}/areas", ['name_en' => 'KM4', 'name_so' => 'KM4 (Afar Kiilo)'])->assertCreated()->json();
        $this->assertSame('KM4 (Afar Kiilo)', $new['name_so']);
        $this->assertFalse($new['needs_review']); // typed by staff

        // Edit names: marks them checked.
        $hodan = Area::where('slug', 'hodan')->firstOrFail();
        $this->assertTrue($hodan->needs_review);
        $this->putJson("/admin/api/areas/{$hodan->id}", ['name_en' => 'Hodan', 'name_so' => 'Hodan'])->assertOk()->assertJson(['needs_review' => false]);
        $wadajir = Area::where('slug', 'wadajir')->firstOrFail();
        $this->putJson("/admin/api/areas/{$wadajir->id}", ['reviewed' => true])->assertOk()->assertJson(['needs_review' => false, 'name_en' => 'Wadajir']);

        // Order: KM4 is last; move it up once.
        $before = $this->mogadishu->areas()->pluck('id')->all();
        $this->assertSame($new['id'], end($before));
        $after = collect($this->postJson("/admin/api/areas/{$new['id']}/move", ['direction' => 'up'])->assertOk()->json())->pluck('id')->all();
        $this->assertSame($new['id'], $after[count($after) - 2]);
        $this->postJson("/admin/api/areas/{$before[0]}/move", ['direction' => 'up'])->assertOk(); // already first: nothing breaks
        $this->assertSame($before[0], $this->mogadishu->areas()->value('id'));
        $this->postJson("/admin/api/areas/{$new['id']}/move", ['direction' => 'sideways'])->assertUnprocessable();

        // Delete: only when no ad uses it.
        Ad::factory()->live()->create(['category_id' => Category::where('slug', 'cars')->value('id'), 'city_id' => $this->mogadishu->id, 'area_id' => $hodan->id]);
        $this->deleteJson("/admin/api/areas/{$hodan->id}")->assertUnprocessable();
        $this->deleteJson("/admin/api/areas/{$new['id']}")->assertOk();
        $this->assertNull(Area::find($new['id']));
        $this->assertSame(1, AdminActivity::where('action', 'area.deleted')->count());

        $list = $this->getJson('/admin/api/locations')->assertOk()->json();
        $hargeisa = collect($list)->pluck('cities')->flatten(1)->firstWhere('slug', 'hargeisa');
        $this->assertSame(6, $hargeisa['areas_review']);
    }

    public function test_only_staff_who_manage_locations(): void
    {
        $this->asStaff('moderator');
        $this->postJson("/admin/api/cities/{$this->mogadishu->id}/areas", ['name_en' => 'KM5'])->assertForbidden();
        $area = Area::firstOrFail();
        $this->postJson("/admin/api/areas/{$area->id}/move", ['direction' => 'up'])->assertForbidden();
        $this->deleteJson("/admin/api/areas/{$area->id}")->assertForbidden();
    }
}
