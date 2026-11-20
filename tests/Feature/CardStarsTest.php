<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\Conversation;
use App\Models\SellerReview;
use App\Models\Shop;
use App\Models\User;
use App\Support\Cache\PageCache;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** PR 85: seller stars on ad cards (switch "ads.card_stars", needs "seller_ratings"). */
class CardStarsTest extends TestCase
{
    use RefreshDatabase;

    private User $rated;

    private Ad $ratedAd;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->switch('seller_ratings', true);
        $this->switch('performance.page_cache', false);
        $this->rated = User::factory()->completed()->create(['name' => 'Rated Seller']);
        $this->ratedAd = $this->car($this->rated, 'Rated Toyota');
        $this->rate($this->rated, [5, 4]); // 4.5 from 2
    }

    private function switch(string $key, bool $on): void
    {
        app(FeatureManager::class)->set($key, $on);
        PageCache::bump();
    }

    private function car(User $seller, string $title): Ad
    {
        return Ad::factory()->live()->create([
            'user_id' => $seller->id, 'title' => $title,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);
    }

    /** @param list<int> $stars */
    private function rate(User $seller, array $stars): void
    {
        $ad = Ad::where('user_id', $seller->id)->firstOrFail();
        foreach ($stars as $s) {
            $buyer = User::factory()->completed()->create();
            $c = Conversation::create(['ad_id' => $ad->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id]);
            $r = new SellerReview(['stars' => $s]);
            $r->forceFill(['seller_id' => $seller->id, 'buyer_id' => $buyer->id, 'conversation_id' => $c->id, 'ad_id' => $ad->id])->save();
        }
        SellerReview::recount($seller->id);
    }

    /** The stars line on the card of the ad with this title, or null. */
    private function starsOn(string $html, string $title): ?string
    {
        foreach (explode('data-card>', $html) as $card) {
            if (str_contains($card, '>'.$title.'</p>')) {
                return preg_match('/data-card-stars>.*?<span aria-hidden="true">([^<]+)<\/span>/s', $card, $m) ? $m[1] : null;
            }
        }
        $this->fail("No card for {$title}");
    }

    public function test_cards_show_the_sellers_stars_in_every_list(): void
    {
        $this->switch('favourites', true);
        $viewer = User::factory()->completed()->create();
        DB::table('favourites')->insert(['user_id' => $viewer->id, 'ad_id' => $this->ratedAd->id, 'created_at' => now(), 'updated_at' => now()]);
        $other = $this->car(User::factory()->completed()->create(), 'Other Vitz');

        foreach (['/', '/cars', '/search?q=Toyota', '/cars/mogadishu', $other->url()] as $url) {
            $this->assertSame('4.5 (2)', $this->starsOn((string) $this->get($url)->assertOk()->getContent(), 'Rated Toyota'), $url);
        }
        $this->assertSame('4.5 (2)', $this->starsOn((string) $this->actingAs($viewer)->get('/account/saved')->assertOk()->getContent(), 'Rated Toyota'), 'saved');

        $html = (string) $this->get('/cars')->getContent();
        $this->assertStringContainsString('Seller rated 4.5 out of 5 by 2 buyers', $html);
        $this->assertNull($this->starsOn($html, 'Other Vitz'), 'no ratings, no stars');
    }

    public function test_somali_text_for_screen_readers(): void
    {
        $this->assertStringContainsString('Iibiyaha waxaa siiyey 4.5 ka mid ah 5 2 iibsade', (string) $this->get('/so/cars')->getContent());
    }

    public function test_not_on_the_sellers_own_page_or_shop(): void
    {
        $this->switch('sellers.page', true);
        $this->switch('shops', true);
        $this->assertStringNotContainsString('data-card-stars', (string) $this->get("/seller/{$this->rated->id}")->getContent());

        $shop = new Shop(['name' => 'Rated Motors', 'slug' => 'rated-motors']);
        $shop->forceFill(['user_id' => $this->rated->id, 'active_until' => now()->addMonth()])->save();
        $this->assertStringNotContainsString('data-card-stars', (string) $this->get('/shop/rated-motors')->assertOk()->getContent());
    }

    public function test_hidden_with_either_switch_off(): void
    {
        $this->switch('ads.card_stars', false);
        $this->assertStringNotContainsString('data-card-stars', (string) $this->get('/cars')->getContent());

        $this->switch('ads.card_stars', true);
        $this->switch('seller_ratings', false);
        $this->assertStringNotContainsString('data-card-stars', (string) $this->get('/cars')->getContent());
    }

    public function test_one_query_for_all_cards(): void
    {
        foreach (range(1, 10) as $i) {
            $seller = User::factory()->completed()->create();
            $this->car($seller, 'Car '.$i);
            $this->rate($seller, [3]);
        }
        $count = function (string $url): int {
            $this->get($url);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get($url)->assertOk();
            $n = collect(DB::getQueryLog())->reject(fn ($q) => str_contains($q['query'], '`cache`'))->count();
            DB::disableQueryLog();

            return $n;
        };
        $on = $count('/cars');
        $this->assertSame(11, substr_count((string) $this->get('/cars')->getContent(), 'data-card-stars')); // 1 + 10 rated sellers

        $this->switch('ads.card_stars', false);
        $this->switch('ads.card_years', false); // the same seller line also carries "2 years on iibsom" (PR 87)
        $this->assertSame($on - 1, $count('/cars'), 'exactly one extra query for the stars');
    }
}
