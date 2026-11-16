<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\AdPhoto;
use App\Models\Category;
use App\Models\City;
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
