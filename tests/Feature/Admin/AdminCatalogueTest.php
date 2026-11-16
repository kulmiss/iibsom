<?php

namespace Tests\Feature\Admin;

use App\Models\Ad;
use App\Models\Area;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Catalog;
use App\Support\Features\FeatureManager;

class AdminCatalogueTest extends AdminTestCase
{
    public function test_moderator_cannot_edit_catalogue(): void
    {
        $this->asStaff('moderator');

        $this->getJson('/admin/api/categories')->assertForbidden();
        $this->getJson('/admin/api/locations')->assertForbidden();
        $this->getJson('/admin/api/features')->assertForbidden();
        $this->getJson('/admin/api/settings/general')->assertForbidden();
    }

    public function test_category_tree_and_icons(): void
    {
        $this->asStaff();

        $data = $this->getJson('/admin/api/categories')->assertOk()->json();
        $this->assertSame('Vehicles', $data['categories'][0]['name_en']);
        $this->assertNotEmpty($data['categories'][0]['children']);
        $this->assertContains('car', $data['icons']);
    }

    public function test_add_a_category_without_code_and_it_shows_on_the_site(): void
    {
        $this->asStaff();
        $vehicles = Category::where('slug', 'vehicles')->first();

        $this->postJson('/admin/api/categories', ['name_en' => 'Boats', 'name_so' => 'Doonyo', 'parent_id' => $vehicles->id, 'icon' => 'truck', 'enabled' => true])
            ->assertCreated()->assertJsonPath('slug', 'boats');

        $this->assertContains('boats', collect(app(Catalog::class)->categories())->firstWhere('slug', 'vehicles')['children'] ? collect(collect(app(Catalog::class)->categories())->firstWhere('slug', 'vehicles')['children'])->pluck('slug') : []);
    }

    public function test_bad_icon_or_third_level_is_refused(): void
    {
        $this->asStaff();
        $cars = Category::where('slug', 'cars')->first();

        $this->postJson('/admin/api/categories', ['name_en' => 'X1', 'icon' => 'not-an-icon'])->assertUnprocessable();
        $this->postJson('/admin/api/categories', ['name_en' => 'Sedans', 'icon' => 'car', 'parent_id' => $cars->id])->assertUnprocessable();
    }

    public function test_turn_category_off_hides_it(): void
    {
        $this->asStaff();
        $jobs = Category::where('slug', 'jobs')->first();

        $this->putJson("/admin/api/categories/{$jobs->id}", ['name_en' => 'Jobs', 'icon' => 'briefcase', 'enabled' => false])->assertOk();
        $this->get('/')->assertDontSee('>Jobs<', false);
    }

    public function test_category_with_ads_cannot_be_deleted(): void
    {
        $this->asStaff();
        $cars = Category::where('slug', 'cars')->first();
        Ad::factory()->create(['category_id' => $cars->id]);

        $this->deleteJson("/admin/api/categories/{$cars->id}")->assertUnprocessable();
        $this->deleteJson('/admin/api/categories/'.Category::where('slug', 'boats-x')->value('id'))->assertNotFound();
    }

    public function test_add_select_field_and_it_appears_on_post_form(): void
    {
        $this->asStaff();
        $phones = Category::where('slug', 'mobile-phones')->first();

        $this->postJson("/admin/api/categories/{$phones->id}/fields", [
            'key' => 'colour', 'type' => 'select', 'label_en' => 'Colour', 'options' => ['Black', 'White', 'Gold', 'Black'], 'required' => false,
        ])->assertCreated()->assertJsonPath('options', ['Black', 'White', 'Gold']);

        $seller = User::factory()->completed()->create();
        $this->actingAs($seller)->get('/post/mobile-phones')->assertSee('name="fields[colour]"', false)->assertSee('Gold');
    }

    public function test_field_key_rules(): void
    {
        $this->asStaff();
        $cars = Category::where('slug', 'cars')->first();

        $this->postJson("/admin/api/categories/{$cars->id}/fields", ['key' => 'Bad Key', 'type' => 'text', 'label_en' => 'X'])->assertUnprocessable();
        $this->postJson("/admin/api/categories/{$cars->id}/fields", ['key' => 'brand', 'type' => 'text', 'label_en' => 'Brand'])->assertUnprocessable(); // exists
        $this->postJson("/admin/api/categories/{$cars->id}/fields", ['key' => 'color', 'type' => 'select', 'label_en' => 'Colour'])->assertUnprocessable(); // options needed
    }

    public function test_field_from_other_category_cannot_be_edited_via_wrong_url(): void
    {
        $this->asStaff();
        $cars = Category::where('slug', 'cars')->first();
        $land = Category::where('slug', 'land')->first();
        $field = $cars->fields()->first();

        $this->putJson("/admin/api/categories/{$land->id}/fields/{$field->id}", ['key' => 'x', 'type' => 'text', 'label_en' => 'X'])->assertNotFound();
    }

    public function test_somali_names_of_cities_and_areas_can_be_changed_and_removed(): void
    {
        $this->asStaff();
        $city = City::where('slug', 'kismayo')->first();

        $this->putJson("/admin/api/cities/{$city->id}", ['name_so' => 'Kismaanyo'])->assertOk()->assertJsonPath('name_so', 'Kismaanyo')->assertJsonPath('name_en', 'Kismayo');
        $this->putJson("/admin/api/cities/{$city->id}", ['enabled' => true])->assertOk()->assertJsonPath('name_so', 'Kismaanyo'); // not sent = kept
        $this->putJson("/admin/api/cities/{$city->id}", ['name_so' => null])->assertOk()->assertJsonPath('name_so', null);       // removed
        $this->assertSame(['en' => 'Kismayo'], $city->fresh()->name);

        $area = Area::first();
        $this->putJson("/admin/api/areas/{$area->id}", ['name_so' => ''])->assertOk()->assertJsonPath('name_so', null);
        $this->assertArrayNotHasKey('so', $area->fresh()->name);
    }

    public function test_cities_on_off_and_new_city_starts_off(): void
    {
        $this->asStaff();
        $kismayo = City::where('slug', 'kismayo')->first();

        $this->putJson("/admin/api/cities/{$kismayo->id}", ['enabled' => true])->assertOk()->assertJsonPath('enabled', true);
        $this->assertContains('kismayo', collect(app(Catalog::class)->cities())->pluck('slug'));

        $this->postJson('/admin/api/cities', ['region_id' => $kismayo->region_id, 'name_en' => 'Afmadow'])->assertCreated()->assertJsonPath('enabled', false);
        $this->postJson('/admin/api/cities', ['region_id' => $kismayo->region_id, 'name_en' => 'Afmadow'])->assertStatus(422);
    }

    public function test_areas_add_and_switch_off(): void
    {
        $this->asStaff();
        $mog = City::where('slug', 'mogadishu')->first();

        $id = $this->postJson("/admin/api/cities/{$mog->id}/areas", ['name_en' => 'KM4'])->assertCreated()->json('id');
        $this->putJson("/admin/api/areas/{$id}", ['enabled' => false])->assertOk()->assertJsonPath('enabled', false);
        $this->getJson("/admin/api/cities/{$mog->id}/areas")->assertOk()->assertJsonFragment(['name_en' => 'KM4', 'enabled' => false]);
    }

    public function test_feature_switches_page_and_toggle(): void
    {
        $this->asStaff();

        $data = $this->getJson('/admin/api/features')->assertOk()->json();
        $this->assertSame('Sign-in', $data['groups'][0]['group']);
        $this->assertGreaterThan(0, $data['summary']['on']);

        $this->putJson('/admin/api/features/contact.whatsapp', ['enabled' => false])->assertOk()->assertJson(['enabled' => false]);
        $this->assertFalse(app(FeatureManager::class)->enabled('contact.whatsapp'));
        $this->getJson('/admin/api/features')->assertJsonFragment(['key' => 'contact.whatsapp', 'enabled' => false, 'changed' => true]);

        $this->deleteJson('/admin/api/features/contact.whatsapp')->assertOk()->assertJson(['enabled' => true]);
        $this->putJson('/admin/api/features/not.real', ['enabled' => true])->assertNotFound();
    }

    public function test_general_settings(): void
    {
        $this->asStaff();

        $this->getJson('/admin/api/settings/general')->assertOk()->assertJsonPath('ads_expiry_days', 60);

        $this->putJson('/admin/api/settings/general', ['ads_expiry_days' => 30, 'ads_max_per_day' => 3, 'ads_max_photos' => 6, 'languages' => ['so']])
            ->assertOk()->assertJsonPath('ads_expiry_days', 30)->assertJsonPath('languages', ['en', 'so']); // English always on

        $this->assertSame(3, setting('ads.max_per_day'));
        $this->putJson('/admin/api/settings/general', ['ads_expiry_days' => 1, 'ads_max_per_day' => 3, 'ads_max_photos' => 6, 'languages' => ['en']])->assertUnprocessable();
    }
}
