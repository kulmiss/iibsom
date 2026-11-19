<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\AdPhoto;
use App\Models\Category;
use App\Models\City;
use App\Models\Conversation;
use App\Models\SellerReview;
use App\Models\Shop;
use App\Models\User;
use App\Support\Features\FeatureManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Query budgets: pages must not do one query per ad (N+1). */
class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private Ad $ad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(FeatureManager::class)->set('performance.page_cache', false);

        $users = User::factory()->completed()->count(5)->create();
        foreach (range(1, 30) as $i) {
            $ad = Ad::factory()->live()->create([
                'user_id' => $users[$i % 5]->id,
                'category_id' => Category::where('slug', $i % 2 ? 'cars' : 'mobile-phones')->value('id'),
                'city_id' => City::where('slug', 'mogadishu')->value('id'),
            ]);
            AdPhoto::factory()->count(2)->create(['ad_id' => $ad->id, 'user_id' => $ad->user_id]);
        }
        $this->ad = Ad::first();
    }

    private function queries(string $url): int
    {
        $this->get($url)->assertOk(); // warm caches
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        $log = collect(DB::getQueryLog())->reject(fn ($q) => str_contains($q['query'], '`cache`'));
        $count = $log->count();
        DB::disableQueryLog();

        return $count;
    }

    public function test_query_budgets(): void
    {
        $this->assertLessThanOrEqual(6, $this->queries('/'), 'home');
        $this->assertLessThanOrEqual(10, $this->queries('/cars'), 'category page');
        $this->assertLessThanOrEqual(10, $this->queries('/search?q=toyota'), 'search');
        $this->assertLessThanOrEqual(14, $this->queries(parse_url($this->ad->url(), PHP_URL_PATH)), 'ad page');
        $this->assertLessThanOrEqual(6, $this->queries('/api/v1/ads'), 'api list');
        $this->assertLessThanOrEqual(6, $this->queries('/categories'), 'all categories');
    }

    /** Phase 2/3 pages with many rows: the count must not grow with the rows (bug hunt D21). */
    public function test_query_budgets_for_phase_two_and_three_pages(): void
    {
        foreach (['ad_stats', 'chat', 'shops', 'seller_ratings', 'favourites', 'verified_badge', 'boosts'] as $switch) {
            app(FeatureManager::class)->set($switch, true);
        }
        $seller = $this->ad->user;
        $shop = new Shop(['name' => 'Abdi Motors', 'slug' => 'abdi-motors']);
        $shop->forceFill(['user_id' => $seller->id, 'active_until' => now()->addMonth()])->save();
        foreach (User::factory()->completed()->count(12)->create() as $buyer) {
            $c = Conversation::create(['ad_id' => $this->ad->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id]);
            $m = $c->messages()->create(['sender_id' => $buyer->id, 'body' => 'Hello, is it available?']);
            $c->forceFill(['last_message_at' => now(), 'last_message_id' => $m->id])->save();
            $review = new SellerReview(['stars' => 4, 'comment' => 'Fine']);
            $review->forceFill(['seller_id' => $seller->id, 'buyer_id' => $buyer->id, 'conversation_id' => $c->id, 'ad_id' => $this->ad->id])->save();
        }
        SellerReview::recount($seller->id);
        $this->actingAs($seller);

        $this->assertLessThanOrEqual(16, $this->queries('/account/ads'), 'my ads with stats');
        $this->assertLessThanOrEqual(12, $this->queries('/messages'), 'chat list');
        $this->assertLessThanOrEqual(12, $this->queries('/account/payments'), 'payments');
        auth()->logout();
        $this->assertLessThanOrEqual(10, $this->queries('/shop/abdi-motors'), 'shop page');
        $this->assertLessThanOrEqual(10, $this->queries("/seller/{$seller->id}/reviews"), 'ratings page');
    }

    public function test_cached_home_needs_no_database(): void
    {
        app(FeatureManager::class)->set('performance.page_cache', true);
        $this->get('/');
        DB::enableQueryLog();
        $this->get('/')->assertHeader('X-Page-Cache', 'HIT');
        $this->assertSame(0, collect(DB::getQueryLog())->reject(fn ($q) => str_contains($q['query'], '`cache`'))->count());
    }
}
