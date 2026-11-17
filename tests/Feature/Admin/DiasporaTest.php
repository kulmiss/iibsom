<?php

namespace Tests\Feature\Admin;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Features\FeatureManager;
use Illuminate\Support\Facades\Cache;

class DiasporaTest extends AdminTestCase
{
    /** @var array<string, mixed> */
    private array $base = ['ads_expiry_days' => 60, 'ads_max_per_day' => 5, 'ads_max_photos' => 8, 'languages' => ['en']];

    private function ad(?int $price = 6500): Ad
    {
        return Ad::factory()->live()->create([
            'user_id' => User::factory()->completed()->create()->id,
            'price' => $price, 'price_negotiable' => $price === null,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);
    }

    public function test_switch_off_by_default_and_nothing_on_the_ad_page(): void
    {
        $this->assertFalse(config('features')['diaspora']['default']);
        $this->get($this->ad()->url())->assertOk()->assertDontSee('data-diaspora', false)->assertDontSee('Buying for family from abroad?');
    }

    public function test_rates_are_saved_with_the_date_and_refresh_cached_pages(): void
    {
        app(FeatureManager::class)->set('diaspora', true);
        $this->asStaff();
        $this->getJson('/admin/api/settings/general')->assertOk()
            ->assertJsonPath('diaspora_rates.EUR', 0)->assertJsonPath('diaspora_currencies.SEK', 'Swedish krona')
            ->assertJsonPath('diaspora_rates_date', null);
        $this->getJson('/admin/api/me')->assertJsonPath('user.diaspora', true);

        $version = Cache::get('page-cache:version');
        $this->putJson('/admin/api/settings/general', $this->base + ['diaspora_rates' => ['EUR' => '0.92', 'GBP' => '0.79', 'XXX' => 5, 'SEK' => null]])->assertOk();
        $rates = setting('diaspora.rates');
        ksort($rates);
        $this->assertEquals(['AED' => 0, 'AUD' => 0, 'CAD' => 0, 'EUR' => 0.92, 'GBP' => 0.79, 'KES' => 0, 'NOK' => 0, 'SEK' => 0], $rates); // unknown XXX dropped
        $this->assertSame(now('Africa/Mogadishu')->toDateString(), setting('diaspora.rates_date'));
        $this->assertNotSame($version, Cache::get('page-cache:version'));

        $this->putJson('/admin/api/settings/general', $this->base + ['diaspora_rates' => ['EUR' => 'abc']])->assertUnprocessable()->assertJsonValidationErrors('diaspora_rates.EUR');
        $this->putJson('/admin/api/settings/general', $this->base + ['diaspora_rates' => ['EUR' => -1]])->assertUnprocessable();
        $this->assertSame(0.92, setting('diaspora.rates')['EUR']);
    }

    public function test_ad_page_has_the_note_and_only_rates_above_zero(): void
    {
        app(FeatureManager::class)->set('diaspora', true);
        $this->asStaff();
        $this->putJson('/admin/api/settings/general', $this->base + ['diaspora_rates' => ['EUR' => 0.92, 'SEK' => 10.5]])->assertOk();
        auth()->logout();

        $res = $this->get($this->ad()->url())->assertOk()
            ->assertSee('Buying for family from abroad?')
            ->assertSee('data-usd="6500"', false)
            ->assertSee('<option value="EUR">EUR</option>', false)
            ->assertDontSee('<option value="GBP">', false); // 0 = not offered
        $this->assertStringContainsString('"EUR":0.92', html_entity_decode((string) $res->getContent()));
        $this->assertEmpty($res->headers->getCookies(), 'Still a cookie-free guest page.');

        // No price ("negotiable"): the note, but no money line.
        $this->get($this->ad(null)->url())->assertOk()->assertSee('Buying for family from abroad?')->assertDontSee('data-diaspora-price', false);
    }
}
