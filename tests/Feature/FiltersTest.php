<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\AdPhoto;
use App\Models\Area;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
    }

    /** @param array<string, mixed> $attrs */
    private function car(string $title, array $attrs = []): Ad
    {
        return Ad::factory()->live()->create(array_merge([
            'user_id' => User::factory()->completed()->create()->id,
            'title' => $title,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
            'price' => 5000,
        ], $attrs));
    }

    public function test_area_posted_photos_verified_and_popular(): void
    {
        $hodan = Area::where('slug', 'hodan')->firstOrFail();
        $old = $this->car('Old Toyota far away', ['published_at' => now()->subDays(10)]);
        $new = $this->car('New Nissan in Hodan', ['area_id' => $hodan->id, 'views' => 50]);
        AdPhoto::factory()->create(['ad_id' => $new->id, 'user_id' => $new->user_id]);
        $verifiedSeller = User::factory()->completed()->create(['verified_at' => now()]);
        $this->car('Verified seller Honda', ['user_id' => $verifiedSeller->id, 'views' => 99]);

        $this->get('/cars/mogadishu?area='.$hodan->id)->assertOk()->assertSee('New Nissan in Hodan')->assertDontSee('Old Toyota far away');
        $this->get('/cars?posted=7d')->assertOk()->assertSee('New Nissan in Hodan')->assertDontSee('Old Toyota far away');
        $this->get('/cars?posted=24h')->assertOk()->assertSee('Verified seller Honda');
        $this->get('/cars?photos=1')->assertOk()->assertSee('New Nissan in Hodan')->assertDontSee('Verified seller Honda');
        $processing = $this->car('Photo still processing');
        AdPhoto::factory()->create(['ad_id' => $processing->id, 'user_id' => $processing->user_id, 'status' => 'processing']);
        $this->get('/cars?photos=1')->assertOk()->assertDontSee('Photo still processing'); // only ready photos count
        $processing->delete();
        $this->get('/cars?posted=1y')->assertRedirect(); // only known values

        // Verified sellers: only while that switch is ON (otherwise the filter does nothing and is not shown).
        $this->get('/cars?verified=1')->assertOk()->assertSee('Old Toyota far away')->assertDontSee('Only verified sellers');
        app(FeatureManager::class)->set('verified_badge', true);
        $this->get('/cars?verified=1')->assertOk()->assertSee('Verified seller Honda')->assertDontSee('Old Toyota far away');

        // Most viewed: only while ad stats are ON.
        $this->get('/cars')->assertOk()->assertDontSee('value="popular"', false);
        // Without view numbers, "popular" in an old link quietly means newest.
        $this->get('/cars?sort=popular')->assertOk()->assertSeeInOrder(['Verified seller Honda', 'New Nissan in Hodan', 'Old Toyota far away']);
        $old->forceFill(['views' => 1000])->save();
        $this->get('/cars?sort=popular')->assertOk()->assertSeeInOrder(['Verified seller Honda', 'New Nissan in Hodan', 'Old Toyota far away']);
        app(FeatureManager::class)->set('ad_stats', true);
        $this->get('/cars?sort=popular')->assertOk()->assertSeeInOrder(['Old Toyota far away', 'Verified seller Honda', 'New Nissan in Hodan']);
    }

    public function test_price_ranges_with_counts(): void
    {
        foreach ([50, 80, 300, 7000, 25000] as $p) {
            $this->car("Car priced {$p}", ['price' => $p]);
        }
        $html = (string) $this->get('/cars')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#Under \$100</span><span class="text-xs text-stone-500">2</span>#', $html);
        $this->assertMatchesRegularExpression('#\$100 – \$500</span><span class="text-xs text-stone-500">1</span>#', $html);
        $this->assertMatchesRegularExpression('#Over \$20,000</span><span class="text-xs text-stone-500">1</span>#', $html);
        $this->assertStringNotContainsString('$500 – $1,000', $html, 'Empty ranges are hidden.');

        // A range is a link that sets min and max (and keeps the other filters).
        $this->assertStringContainsString(url('/cars').'?condition=used&amp;min=100&amp;max=499', (string) $this->get('/cars?condition=used')->getContent());
        $this->get('/cars?min=100&max=499')->assertOk()->assertSee('Car priced 300')->assertDontSee('Car priced 80');
    }

    public function test_active_filter_chips_remove_one_filter(): void
    {
        $this->car('Some car');
        $html = (string) $this->get('/cars?condition=used&min=100&photos=1&f[brand]=toyota')->assertOk()
            ->assertSee('aria-label="Filters in use"', false)->assertSee('Clear all')->getContent();
        $this->assertStringContainsString('aria-label="Remove filter: Used"', $html);
        $this->assertStringContainsString('href="'.url('/cars').'?min=100&amp;photos=1&amp;f%5Bbrand%5D=toyota"', $html, 'Removing "Used" keeps the rest.');
        $this->assertStringContainsString('Brand: Toyota', $html);
        $this->assertStringContainsString('noindex', $html);
    }

    public function test_count_endpoint_is_json_and_cookie_free(): void
    {
        $this->car('Cheap one', ['price' => 100]);
        $this->car('Dear one', ['price' => 9000]);

        $res = $this->getJson('/browse-count?category=cars&max=500')->assertOk()->assertJson(['count' => 1, 'text' => 'Show 1 result']);
        $this->assertSame([], $res->headers->getCookies());
        $this->getJson('/browse-count?category=cars')->assertJson(['count' => 2]);
        $this->getJson('/so/browse-count?category=cars')->assertJsonPath('text', 'Tus 2 natiijo');

        // Brand page (/cars/mogadishu/toyota): the number counts only that brand.
        $this->car('A Toyota', ['fields' => ['brand' => 'toyota']]);
        $html = (string) $this->get('/cars/mogadishu/toyota')->assertOk()->getContent();
        $this->assertStringContainsString('brand=toyota', $html);
        $this->getJson('/browse-count?category=cars&city=mogadishu&brand=toyota')->assertJson(['count' => 1]);
        $this->getJson('/browse-count?category=cars&city=mogadishu&brand=no-such-brand')->assertJson(['count' => 3]); // unknown: ignored
    }

    public function test_sheet_and_sidebar_markup(): void
    {
        $this->car('A car');
        $this->get('/cars')->assertOk()
            ->assertSee('id="filters"', false)->assertSee('href="#filters"', false)
            ->assertSee('data-count-text', false)->assertSee('Show 1 result')
            ->assertSee('data-view-toggle', false)
            ->assertSee('Show all categories')
            ->assertDontSee('noindex', false);
    }

    public function test_switch_off_keeps_only_the_basic_filters(): void
    {
        $hodan = Area::where('slug', 'hodan')->firstOrFail();
        $this->car('Old Toyota far away', ['published_at' => now()->subDays(10), 'price' => 50]);
        $this->car('New Nissan in Hodan', ['area_id' => $hodan->id]);
        app(FeatureManager::class)->set('ad_stats', true);
        app(FeatureManager::class)->set('verified_badge', true);
        app(FeatureManager::class)->set('search.more_filters', false);

        // Old links with the extra filters still work: the extra filters are ignored.
        $html = (string) $this->get('/cars/mogadishu?posted=7d&photos=1&verified=1&area='.$hodan->id.'&sort=popular')->assertOk()
            ->assertSee('Old Toyota far away')->assertSee('New Nissan in Hodan')->getContent();
        foreach (['name="posted"', 'name="photos"', 'name="verified"', 'name="area"', 'value="popular"', 'Under $100', 'Remove filter: Last 7 days'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $html, $hidden);
        }
        $this->assertStringContainsString('data-count-url=""', $html, 'No live number while OFF.');
        $this->getJson('/browse-count?category=cars')->assertNotFound();
        // The basic filters still work.
        $this->get('/cars?max=100')->assertOk()->assertSee('Old Toyota far away')->assertDontSee('New Nissan in Hodan');
    }

    public function test_grid_and_list_buttons_are_on_phones_too(): void
    {
        // Found by the checklist run: the buttons were hidden below 640 px (all phones).
        $html = (string) $this->get('/cars')->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<div class="([^"]*)" data-view-toggle>#', $html, $m));
        $this->assertStringNotContainsString('hidden', $m[1]);
    }
}
