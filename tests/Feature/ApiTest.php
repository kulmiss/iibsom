<?php

namespace Tests\Feature;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Catalog;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function ad(array $attrs = []): Ad
    {
        return Ad::factory()->live()->create(array_merge([
            'user_id' => User::factory()->completed()->create(['phone' => '+252615551234'])->id,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ], $attrs));
    }

    public function test_categories_and_cities(): void
    {
        $this->getJson('/api/v1/categories')->assertOk()
            ->assertJsonPath('data.0.slug', 'vehicles')
            ->assertJsonPath('data.0.children.0.slug', 'cars');
        $this->getJson('/api/v1/cities')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'mogadishu');
    }

    public function test_ads_list_only_live_with_filters_and_paging(): void
    {
        $this->ad(['title' => 'Toyota Vitz clean', 'price' => 5000]);
        $this->ad(['title' => 'Nissan Note cheap', 'price' => 3000]);
        $this->ad(['title' => 'Hidden pending car', 'status' => AdStatus::Pending]);
        $this->ad(['title' => 'Demo car', 'is_demo' => true]);

        $this->getJson('/api/v1/ads')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/ads?category=cars&sort=cheapest')->assertJsonPath('data.0.title', 'Nissan Note cheap');
        $this->getJson('/api/v1/ads?max=4000')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/ads?category=no-such-thing')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/ads?per_page=500')->assertUnprocessable();
    }

    public function test_ad_detail_has_no_phone_number_and_hidden_ads_are_404(): void
    {
        $ad = $this->ad(['title' => 'Toyota Vitz clean']);
        $pending = $this->ad(['status' => AdStatus::Pending]);

        $res = $this->getJson("/api/v1/ads/{$ad->id}")->assertOk()
            ->assertJsonPath('data.title', 'Toyota Vitz clean')
            ->assertJsonStructure(['data' => ['description', 'photos', 'seller' => ['name'], 'fields']]);
        $this->assertStringNotContainsString('615551234', $res->getContent());
        $this->assertStringNotContainsString('@', json_encode($res->json('data.seller')));

        $this->getJson("/api/v1/ads/{$pending->id}")->assertNotFound();
    }

    public function test_no_cookies_and_language(): void
    {
        $res = $this->getJson('/api/v1/categories');
        $this->assertSame([], $res->headers->getCookies());

        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->getJson('/api/v1/categories?lang=so')->assertJsonPath('data.0.name', 'Gaadiid')->assertHeader('Content-Language', 'so');
        $this->getJson('/api/v1/categories?lang=xx')->assertJsonPath('data.0.name', 'Vehicles');
    }

    public function test_switch_off_and_rate_limit(): void
    {
        app(FeatureManager::class)->set('api', false);
        $this->getJson('/api/v1/categories')->assertNotFound();
        app(FeatureManager::class)->set('api', true);

        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1/cities');
        }
        $this->getJson('/api/v1/cities')->assertStatus(429);
    }

    public function test_category_fields_for_posting(): void
    {
        $this->getJson('/api/v1/categories/cars/fields')->assertOk()
            ->assertJsonPath('data.category', 'cars')
            ->assertJsonPath('data.has_condition', true)
            ->assertJsonFragment(['key' => 'brand', 'type' => 'select']);
        $this->getJson('/api/v1/categories/vehicles/fields')->assertNotFound(); // top level: no posting
        $this->getJson('/api/v1/categories/no-such/fields')->assertNotFound();

        Category::where('slug', 'vehicles')->update(['enabled' => false]);
        app(Catalog::class)->flush();
        $this->getJson('/api/v1/categories/cars/fields')->assertNotFound(); // parent OFF
    }

    /** Load test: the total is kept in the cache, but any change of an ad shows at once. */
    public function test_total_is_cached_but_follows_every_change(): void
    {
        $first = $this->ad();
        $this->getJson('/api/v1/ads')->assertJsonPath('meta.total', 1);
        $this->ad();
        $this->getJson('/api/v1/ads')->assertJsonPath('meta.total', 2);
        $first->delete();
        $this->getJson('/api/v1/ads')->assertJsonPath('meta.total', 1);
        $this->get('/search')->assertOk()->assertSee('1');
    }
}
