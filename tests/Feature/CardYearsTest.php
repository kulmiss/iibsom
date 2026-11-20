<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Cache\PageCache;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** PR 87: "2 years on iibsom" on ad cards (switch "ads.card_years", needs "sellers.badges"). */
class CardYearsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->switch('performance.page_cache', false);
    }

    private function switch(string $key, bool $on): void
    {
        app(FeatureManager::class)->set($key, $on);
        PageCache::bump();
    }

    private function car(string $title, \DateTimeInterface $joined): Ad
    {
        $seller = User::factory()->completed()->create();
        $seller->forceFill(['created_at' => $joined])->save();

        return Ad::factory()->live()->create([
            'user_id' => $seller->id, 'title' => $title,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);
    }

    private function cardOf(string $html, string $title): string
    {
        foreach (explode('data-card>', $html) as $card) {
            if (str_contains($card, '>'.$title.'</p>')) {
                return $card;
            }
        }
        $this->fail("No card for {$title}");
    }

    public function test_cards_show_whole_years_only(): void
    {
        $this->car('Old Seller Car', now()->subYears(2)->subMonth());
        $this->car('One Year Car', now()->subYear()->subDay());
        $this->car('New Seller Car', now()->subMonths(5));

        $html = (string) $this->get('/cars')->assertOk()->getContent();
        $this->assertStringContainsString('2 years on iibsom', $this->cardOf($html, 'Old Seller Car'));
        $this->assertStringContainsString('1 year on iibsom', $this->cardOf($html, 'One Year Car'));
        $this->assertStringNotContainsString('data-card-years', $this->cardOf($html, 'New Seller Car'), 'months are not shown on cards');

        foreach (['/', '/search?q=Car', '/cars/mogadishu'] as $url) {
            $this->assertStringContainsString('2 years on iibsom', $this->cardOf((string) $this->get($url)->getContent(), 'Old Seller Car'), $url);
        }
        $this->assertStringContainsString('2 sano ku jira iibsom', (string) $this->get('/so/cars')->getContent());
    }

    public function test_not_on_the_sellers_own_page_and_switches_off(): void
    {
        $ad = $this->car('Old Seller Car', now()->subYears(3));
        $this->switch('sellers.page', true);
        $this->assertStringNotContainsString('data-card-years', (string) $this->get("/seller/{$ad->user_id}")->getContent());

        $this->switch('ads.card_years', false);
        $this->assertStringNotContainsString('data-card-years', (string) $this->get('/cars')->getContent());
        $this->switch('ads.card_years', true);
        $this->switch('sellers.badges', false);
        $this->assertStringNotContainsString('data-card-years', (string) $this->get('/cars')->getContent());
    }

    public function test_one_query_for_all_cards_shared_with_the_stars(): void
    {
        foreach (range(1, 8) as $i) {
            $this->car('Car '.$i, now()->subYears(2));
        }
        $count = function (): int {
            $this->get('/cars');
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get('/cars')->assertOk();
            $n = collect(DB::getQueryLog())->reject(fn ($q) => str_contains($q['query'], '`cache`'))->count();
            DB::disableQueryLog();

            return $n;
        };
        $years = $count();
        $this->assertSame(8, substr_count((string) $this->get('/cars')->getContent(), 'data-card-years'));

        $this->switch('seller_ratings', true); // stars too: still the same one query
        $this->assertSame($years, $count());

        $this->switch('ads.card_years', false);
        $this->switch('seller_ratings', false);
        $this->assertSame($years - 1, $count(), 'exactly one extra query for the seller line');
    }
}
