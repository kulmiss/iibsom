<?php

namespace Tests\Feature;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Support\Cache\PageCache;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** PR 83: "Similar ads usually ask $X – $Y" on the ad page, from real prices only. */
class MarketPriceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
    }

    /** @param array<string, mixed> $attributes */
    private function car(?int $price, string $brand = 'toyota', array $attributes = []): Ad
    {
        return Ad::factory()->live()->create($attributes + [
            'price' => $price, 'condition' => 'used', 'published_at' => now()->subDays(3),
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'fields' => ['brand' => $brand, 'year' => 2012],
        ]);
    }

    /** Five similar Toyotas asking 5,000 … 9,000. */
    private function market(): void
    {
        foreach ([5000, 6000, 7000, 8000, 9000] as $p) {
            $this->car($p);
        }
    }

    private function box(Ad $ad, string $prefix = ''): ?string
    {
        Cache::flush();
        PageCache::bump();
        $html = (string) $this->get($prefix.parse_url($ad->url(), PHP_URL_PATH))->assertOk()->getContent();
        $start = strpos($html, 'data-market-price');

        return $start === false ? null : substr($html, $start, 1200);
    }

    public function test_usual_price_from_similar_ads_with_this_ad_left_out(): void
    {
        $this->market();
        $ad = $this->car(7000);

        $box = (string) $this->box($ad);
        $this->assertStringContainsString('Similar Toyota ads usually ask $6,000 – $8,000', $box);
        $this->assertStringContainsString('(from 5 ads, last 6 months)', $box); // not 6: this ad is not counted
        $this->assertStringContainsString('data-place="within"', $box);
        $this->assertStringContainsString('This price is in the usual range.', $box);

        $this->assertStringContainsString(__('ads.market.within', [], 'so'), (string) $this->box($ad, '/so'));
    }

    public function test_lower_much_lower_and_higher_prices(): void
    {
        $this->market();

        $this->assertStringContainsString('data-place="below"', (string) $this->box($this->car(5500)));
        $far = (string) $this->box($this->car(1500));
        $this->assertStringContainsString('data-place="far_below"', $far);
        $this->assertStringContainsString('See the item before you pay anything.', $far);
        $this->assertStringContainsString('You can make an offer.', (string) $this->box($this->car(12000)));
    }

    public function test_hidden_with_fewer_than_five_similar_ads(): void
    {
        foreach ([5000, 6000, 7000, 8000] as $p) {
            $this->car($p);
        }
        $this->assertNull($this->box($this->car(7000)));
    }

    public function test_falls_back_to_the_whole_category_when_the_brand_has_too_few(): void
    {
        foreach ([5000, 6000, 7000, 8000, 9000] as $p) {
            $this->car($p, 'nissan');
        }
        $this->assertStringContainsString('Similar Cars ads usually ask', (string) $this->box($this->car(7000, 'mazda')));
    }

    public function test_hidden_without_a_price_when_not_live_and_with_the_switch_off(): void
    {
        $this->market();
        $this->assertNull($this->box($this->car(null)));

        $waiting = $this->car(7000, 'toyota', ['status' => AdStatus::Pending, 'published_at' => null]);
        Cache::flush();
        $html = (string) $this->actingAs($waiting->user)->get($waiting->url())->assertOk()->getContent();
        $this->assertStringNotContainsString('data-market-price', $html);
        auth()->logout();

        app(FeatureManager::class)->set('ads.market_price', false);
        $this->assertNull($this->box($this->car(7000)));
    }

    public function test_the_usual_price_costs_one_query_at_most(): void
    {
        $this->market();
        $ad = $this->car(7000);
        $path = (string) parse_url($ad->url(), PHP_URL_PATH);

        $count = function () use ($path): int {
            Cache::flush();
            PageCache::bump();
            $n = 0;
            DB::listen(function () use (&$n) {
                $n++;
            });
            $this->get($path)->assertOk();

            return $n;
        };

        $on = $count();
        app(FeatureManager::class)->set('ads.market_price', false);
        $off = $count();
        $this->assertLessThanOrEqual(1, $on - $off, "on: {$on}, off: {$off}"); // the price guide: one query, then kept 1 hour
    }
}
