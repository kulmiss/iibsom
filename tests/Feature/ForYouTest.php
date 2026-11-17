<?php

namespace Tests\Feature;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Features\FeatureManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForYouTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function ad(string $category, array $attrs = []): Ad
    {
        return Ad::factory()->live()->create(array_merge([
            'user_id' => User::factory()->completed()->create()->id,
            'category_id' => Category::where('slug', $category)->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ], $attrs));
    }

    public function test_recommends_ads_from_viewed_categories_but_not_seen_ones(): void
    {
        $seen = $this->ad('cars', ['title' => 'Seen car ad here']);
        $this->ad('cars', ['title' => 'Another car ad']);
        $this->ad('motorcycles', ['title' => 'Unrelated phone ad']);

        $res = $this->getJson('/for-you?'.http_build_query(['c' => [Category::where('slug', 'cars')->value('id')], 'v' => [$seen->id]]))
            ->assertOk()->assertHeader('Cache-Control', 'max-age=120, private');

        $this->assertStringContainsString('Another car ad', $res->json('recommended'));
        $this->assertStringNotContainsString('Seen car ad here', $res->json('recommended'));
        $this->assertStringNotContainsString('Unrelated phone ad', $res->json('recommended'));
        $this->assertStringContainsString('Seen car ad here', $res->json('recent'));
    }

    public function test_main_category_includes_its_sub_categories(): void
    {
        $this->ad('cars', ['title' => 'Car inside vehicles']);
        $vehicles = Category::where('slug', 'cars')->value('parent_id');

        $this->assertStringContainsString('Car inside vehicles', $this->getJson('/for-you?c[]='.$vehicles)->json('recommended'));
    }

    public function test_hidden_ads_never_show(): void
    {
        $pending = $this->ad('cars', ['title' => 'Pending one', 'status' => AdStatus::Pending]);
        $demo = $this->ad('cars', ['title' => 'Demo one', 'is_demo' => true]);

        $res = $this->getJson('/for-you?'.http_build_query(['c' => [Category::where('slug', 'cars')->value('id')], 'v' => [$pending->id, $demo->id]]))->assertOk();
        $this->assertNull($res->json('recommended'));
        $this->assertNull($res->json('recent'));
    }

    public function test_bad_input_is_refused(): void
    {
        $this->getJson('/for-you?c[]=abc')->assertUnprocessable();
        $this->getJson('/for-you?'.http_build_query(['v' => range(1, 40)]))->assertUnprocessable();
    }

    public function test_nothing_is_stored_and_no_tracking_cookie(): void
    {
        $tables = ['sessions'];
        $this->getJson('/for-you?c[]=1')->assertOk();
        foreach ($tables as $t) {
            $this->assertSame(0, \DB::table($t)->count());
        }
    }

    public function test_pages_carry_tracking_marks_only_when_switch_on(): void
    {
        $ad = $this->ad('cars');
        $this->get($ad->url())->assertSee('data-track-ad="'.$ad->id.'"', false);
        $this->get('/')->assertSee('data-for-you', false);

        app(FeatureManager::class)->set('recommendations', false);
        $this->get($ad->url())->assertDontSee('data-track-ad', false);
        $this->get('/')->assertDontSee('data-for-you', false);
        $this->getJson('/for-you?c[]=1')->assertNotFound();
    }
}
