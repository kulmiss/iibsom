<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_home_shows_search_categories_and_popular_tiles(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('What are you looking for?')
            ->assertSee('I am looking for...', false)
            ->assertSee('Vehicles')
            ->assertSee('Livestock &amp; Animals', false)
            ->assertSee('Popular categories')
            ->assertSee('Trending ads')
            ->assertSee('No ads yet');
    }

    public function test_phone_tiles_start_with_post_ad_and_trending(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertLessThan(strpos($html, '>Trending<'), strpos($html, '>Post ad<'));
        $this->assertStringContainsString('href="#trending"', $html);
        $this->assertStringContainsString('id="trending"', $html);
    }

    public function test_location_picker_has_only_live_cities(): void
    {
        // Only Mogadishu is live → no picker at all.
        $this->get('/')
            ->assertDontSee('<select id="city"', false)
            ->assertDontSee('All Somalia');

        City::where('slug', 'kismayo')->first()->update(['enabled' => true]);

        $this->get('/')
            ->assertSee('<select id="city"', false)
            ->assertSee('All Somalia')
            ->assertSee('<option value="mogadishu">Mogadishu</option>', false)
            ->assertSee('Kismayo');
    }

    public function test_disabled_category_disappears_from_home(): void
    {
        Category::where('slug', 'jobs')->first()->update(['enabled' => false]);

        $this->get('/')->assertDontSee('>Jobs<', false);
    }

    public function test_ad_counts_show(): void
    {
        Category::where('slug', 'vehicles')->first()->update(['ads_count' => 28626]);

        $this->get('/')->assertSee('28,626 ads');
    }

    public function test_icons_are_inline_and_decorative(): void
    {
        $this->get('/')->assertSee('<svg class="size-5" aria-hidden="true"', false);
    }

    public function test_home_uses_few_database_queries(): void
    {
        $this->get('/'); // warm caches

        DB::enableQueryLog();
        $this->get('/')->assertOk();

        // Budget: switches, settings and lists all come from the cache.
        // (The `cache` table is ignored: on shared hosting the cache lives in the database.)
        $queries = collect(DB::getQueryLog())->reject(fn ($q) => str_contains($q['query'], '`cache`'));
        $this->assertLessThanOrEqual(2, $queries->count(), $queries->toJson());
    }
}
