<?php

namespace Tests\Feature;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Ads\PriceHint;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PriceHintTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Category $cars;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(FeatureManager::class)->set('price_suggestions', true);
        $this->seller = User::factory()->completed()->create();
        $this->cars = Category::where('slug', 'cars')->firstOrFail();
    }

    /** @param array<string, mixed> $attrs */
    private function car(int $price, string $brand = 'toyota', string $model = 'Vitz', array $attrs = []): Ad
    {
        return Ad::factory()->live()->create(array_merge([
            'category_id' => $this->cars->id,
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
            'price' => $price,
            'condition' => 'used',
            'fields' => ['brand' => $brand, 'model' => $model],
        ], $attrs));
    }

    private function hint(string $query = ''): mixed
    {
        Cache::flush();

        return $this->actingAs($this->seller)->getJson('/post/cars/price-hint'.$query)->assertOk()->json('hint');
    }

    public function test_switch_off_by_default_and_404_when_off(): void
    {
        $this->assertFalse(config('features')['price_suggestions']['default']);
        app(FeatureManager::class)->set('price_suggestions', false);

        $this->actingAs($this->seller)->getJson('/post/cars/price-hint')->assertNotFound();
        $this->actingAs($this->seller)->get('/post/cars')->assertOk()->assertDontSee('data-price-hint', false);
    }

    public function test_guests_and_top_categories_are_refused(): void
    {
        $this->getJson('/post/cars/price-hint')->assertUnauthorized();
        $this->actingAs($this->seller)->getJson('/post/vehicles/price-hint')->assertNotFound();
        $this->actingAs($this->seller)->get('/post/cars')->assertOk()->assertSee('data-price-hint', false)->assertDontSee('data-except', false);

        // The edit form leaves the ad's own price out.
        $mine = $this->car(5000, attrs: ['user_id' => $this->seller->id]);
        $this->actingAs($this->seller)->get("/account/ads/{$mine->id}/edit")->assertOk()->assertSee('data-except="'.$mine->id.'"', false);
    }

    public function test_needs_at_least_five_ads(): void
    {
        foreach ([1000, 2000, 3000, 4000] as $p) {
            $this->car($p);
        }
        $this->assertNull($this->hint());

        $this->car(5000);
        $hint = $this->hint();
        $this->assertSame(['low' => 2000, 'high' => 4000, 'middle' => 3000, 'count' => 5, 'scope' => 'category'], array_intersect_key($hint, array_flip(['low', 'high', 'middle', 'count', 'scope'])));
        $this->assertSame('Most Cars ads are $2,000 – $4,000', $hint['range']);
    }

    public function test_closest_match_first_brand_model_then_brand(): void
    {
        foreach ([4000, 4500, 5000, 5500, 6000] as $p) {
            $this->car($p, 'toyota', 'Vitz');
        }
        foreach ([20000, 21000, 22000] as $p) {
            $this->car($p, 'toyota', 'Land Cruiser');
        }
        foreach ([1000, 1100] as $p) {
            $this->car($p, 'nissan', 'Note');
        }

        $vitz = $this->hint('?fields[brand]=toyota&fields[model]=vitz'); // any letter case
        $this->assertSame('brand_model', $vitz['scope']);
        $this->assertSame(5000, $vitz['middle']);
        $this->assertSame('Most Toyota vitz ads are $4,500 – $5,500', $vitz['range']);

        $cruiser = $this->hint('?fields[brand]=toyota&fields[model]=Land%20Cruiser');
        $this->assertSame('brand', $cruiser['scope']); // only 3 Land Cruisers → all Toyotas (8)
        $this->assertSame(8, $cruiser['count']);

        $nissan = $this->hint('?fields[brand]=nissan');
        $this->assertSame('category', $nissan['scope']); // 2 Nissans → all cars (10)
        $this->assertSame(10, $nissan['count']);
    }

    public function test_which_ads_count(): void
    {
        foreach ([1000, 2000, 3000, 4000] as $p) {
            $this->car($p);
        }
        // Not counted: pending, rejected, deleted, demo (demo hidden), too old, no price, "new" when asking "used", other category.
        $this->car(9000, attrs: ['status' => AdStatus::Pending]);
        $this->car(9000, attrs: ['status' => AdStatus::Rejected]);
        $this->car(9000)->delete();
        $this->car(9000, attrs: ['is_demo' => true]);
        $this->car(9000, attrs: ['published_at' => now()->subDays(PriceHint::DAYS + 1)]);
        $this->car(9000, attrs: ['price' => null, 'price_negotiable' => true]);
        $this->car(9000, attrs: ['condition' => 'new']);
        $this->car(9000, attrs: ['category_id' => Category::where('slug', 'motorcycles')->value('id')]);
        app(FeatureManager::class)->set('site.demo_data', false);
        $this->assertNull($this->hint('?condition=used'));

        // Sold ads count (they show real prices).
        $sold = $this->car(5000, attrs: ['status' => AdStatus::Sold]);
        $this->assertSame(5, $this->hint('?condition=used')['count']);

        // Editing an ad: its own price is left out.
        $this->assertNull($this->hint('?condition=used&except='.$sold->id));
    }

    public function test_bad_input_is_refused(): void
    {
        $this->actingAs($this->seller)->getJson('/post/cars/price-hint?condition=broken')->assertUnprocessable();
        $this->actingAs($this->seller)->getJson('/post/cars/price-hint?fields[brand][]=x')->assertUnprocessable();
        $this->actingAs($this->seller)->getJson('/post/cars/price-hint?except=abc')->assertUnprocessable();
    }

    public function test_round_and_percentile(): void
    {
        $this->assertSame(87, PriceHint::nice(87.4));
        $this->assertSame(640, PriceHint::nice(643));
        $this->assertSame(5800, PriceHint::nice(5830));
        $this->assertSame(120000, PriceHint::nice(123456));
        $this->assertSame(2.5, PriceHint::percentile([1, 2, 3, 4], 0.5));
        $this->assertSame(7.0, PriceHint::percentile([7], 0.75));
    }

    public function test_somali_text(): void
    {
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        foreach ([1000, 2000, 3000, 4000, 5000] as $p) {
            $this->car($p);
        }
        Cache::flush();
        $this->actingAs($this->seller)->getJson('/so/post/cars/price-hint')->assertOk()
            ->assertJsonPath('hint.range', fn (string $t) => str_starts_with($t, 'Inta badan'));
    }
}
