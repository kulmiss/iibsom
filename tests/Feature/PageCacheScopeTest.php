<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Support\Cache\PageCache;
use App\Support\Features\FeatureManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PR 90: an ad change no longer empties the whole page cache. Its own page refreshes at once; pages that list
 * ads at most once per 30 seconds; pages without ads not at all.
 */
class PageCacheScopeTest extends TestCase
{
    use RefreshDatabase;

    private Ad $a;

    private Ad $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['iibsom.page_cache_list_delay' => 30]);
        app(FeatureManager::class)->set('performance.page_cache', true);
        $cars = Category::where('slug', 'cars')->value('id');
        $this->a = Ad::factory()->live()->create(['title' => 'Alpha car', 'category_id' => $cars]);
        $this->b = Ad::factory()->live()->create(['title' => 'Bravo car', 'category_id' => $cars]);
        // Let the waits from creating them run out (the second one is applied, then its own wait ends too).
        $this->travel(31)->seconds();
        PageCache::version();
        $this->travel(31)->seconds();
        PageCache::bump();
    }

    private function state(string $url): string
    {
        return (string) $this->get($url)->assertOk()->headers->get('X-Page-Cache');
    }

    private function warm(string ...$urls): void
    {
        foreach ($urls as $url) {
            $this->get($url);
            $this->assertSame('HIT', $this->state($url), $url);
        }
    }

    public function test_pages_without_ads_stay_cached_when_ads_change(): void
    {
        $this->warm('/help', '/contact');
        $this->a->update(['price' => 999]);
        $this->assertSame('HIT', $this->state('/help'));
        $this->assertSame('HIT', $this->state('/contact'));

        PageCache::bump(); // a site change still drops them
        $this->assertSame('MISS', $this->state('/help'));
    }

    public function test_the_changed_ad_page_refreshes_at_once_other_ad_pages_stay(): void
    {
        $this->warm($this->a->url(), $this->b->url());
        $this->a->update(['title' => 'Alpha car now cheaper']);

        $this->get($this->a->fresh()->url())->assertSee('Alpha car now cheaper')->assertHeader('X-Page-Cache', 'MISS');
        $this->assertSame('HIT', $this->state($this->b->url()));
    }

    public function test_lists_refresh_at_once_then_at_most_every_30_seconds(): void
    {
        $this->warm('/cars');

        // First change: at once.
        $this->a->update(['title' => 'Alpha car edited']);
        $this->get('/cars')->assertHeader('X-Page-Cache', 'MISS')->assertSee('Alpha car edited');

        // More changes within 30 seconds wait together…
        $this->b->update(['title' => 'Bravo car edited']);
        $this->get('/cars')->assertHeader('X-Page-Cache', 'HIT')->assertDontSee('Bravo car edited');

        // …and show once the wait is over.
        $this->travel(31)->seconds();
        $this->get('/cars')->assertHeader('X-Page-Cache', 'MISS')->assertSee('Bravo car edited');
        $this->assertSame('HIT', $this->state('/cars'));
    }

    public function test_a_removed_ad_is_gone_from_its_page_at_once(): void
    {
        $this->warm($this->a->url(), '/cars');
        $this->a->update(['title' => 'Alpha warm-up']); // uses the "at once" refresh of the lists
        $this->a->delete();

        $this->get($this->a->url())->assertNotFound();
        $this->travel(31)->seconds();
        $this->get('/cars')->assertDontSee('Alpha warm-up');
    }

    public function test_the_version_for_other_caches_follows_batched_ad_changes(): void
    {
        $before = PageCache::version();
        $this->a->update(['price' => 1]);
        $first = PageCache::version();
        $this->assertNotSame($before, $first);

        $this->b->update(['price' => 2]);
        $this->assertSame($first, PageCache::version(), 'waits');
        $this->travel(31)->seconds();
        $this->assertNotSame($first, PageCache::version());
    }

    public function test_delay_zero_keeps_the_old_behaviour(): void
    {
        config(['iibsom.page_cache_list_delay' => 0]);
        $this->warm('/cars');
        $this->a->update(['title' => 'One']);
        $this->get('/cars')->assertSee('One');
        $this->b->update(['title' => 'Two']);
        $this->get('/cars')->assertSee('Two');
    }
}
