<?php

namespace Tests\Feature\Admin;

use App\Models\Ad;
use App\Models\Category;
use App\Support\Cache\PageCache;
use App\Support\Features\FeatureManager;
use Illuminate\Support\Facades\DB;

/** PR 86: Admin → Home page, one on/off per part of the home page. */
class HomePageAdminTest extends AdminTestCase
{
    private function switch(string $key, bool $on): void
    {
        app(FeatureManager::class)->set($key, $on);
        PageCache::bump();
    }

    private function home(): string
    {
        return (string) $this->get('/')->assertOk()->getContent();
    }

    public function test_every_part_has_a_real_switch_and_the_list_is_in_page_order(): void
    {
        $keys = [];
        foreach (config('home.sections') as $s) {
            if (! ($s['fixed'] ?? false)) {
                $this->assertArrayHasKey($s['key'], config('features'), $s['key']);
                $keys[] = $s['key'];
            }
            foreach (array_merge($s['needs'] ?? [], $s['needs_any'] ?? []) as $need) {
                $this->assertArrayHasKey($need, config('features'), $need);
            }
        }
        $this->assertSame($keys, array_values(array_unique($keys)), 'each switch once');
        $this->assertArrayNotHasKey('home.hero_plus', config('features'));

        // Same order as on the page (markers in the home page HTML).
        Ad::factory()->live()->count(3)->create(['category_id' => Category::where('slug', 'cars')->value('id')]);
        DB::table('search_terms')->insert(['term' => 'goats', 'ads' => 16]);
        $html = $this->home();
        $markers = ['data-home-promise', 'data-search-form', 'data-popular-searches', 'data-home-post', 'data-home-quick-links', 'data-home-popular-categories', 'id="trending"'];
        $positions = array_map(fn ($m) => strpos($html, $m), $markers);
        $this->assertNotContains(false, $positions, implode(',', $markers));
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
    }

    public function test_admin_list_shows_parts_with_what_they_still_need(): void
    {
        $this->asStaff();
        $res = $this->getJson('/admin/api/home-page')->assertOk();
        $sections = collect($res->json('sections'));

        $this->assertSame(count(config('home.sections')), $sections->count());
        $this->assertSame(range(1, $sections->count()), $sections->pluck('position')->all());
        $this->assertSame('Big title and search box', $sections[0]['label']);
        $this->assertTrue($sections[0]['fixed']);
        $this->assertSame('Top (blue box)', $sections[0]['area']);

        $popular = $sections->firstWhere('key', 'home.popular_searches');
        $this->assertSame('Popular searches', $popular['label']);
        $this->assertTrue($popular['enabled']);
        $this->assertSame([], $popular['waiting_for']);

        // "Popular now" needs Ad statistics (OFF by default).
        $now = $sections->firstWhere('key', 'home.popular_now');
        $this->assertSame(['ad_stats'], array_column($now['waiting_for'], 'key'));
        // Shops OR verified badge.
        $trusted = $sections->firstWhere('key', 'home.trusted_sellers');
        $this->assertCount(1, $trusted['waiting_for']);
        $this->assertStringContainsString(' or ', $trusted['waiting_for'][0]['label']);

        $this->switch('ad_stats', true);
        $this->switch('shops', true);
        $sections = collect($this->getJson('/admin/api/home-page')->json('sections'));
        $this->assertSame([], $sections->firstWhere('key', 'home.popular_now')['waiting_for']);
        $this->assertSame([], $sections->firstWhere('key', 'home.trusted_sellers')['waiting_for']);
    }

    public function test_hiding_a_part_from_admin_hides_it_on_the_cached_home_page(): void
    {
        DB::table('search_terms')->insert(['term' => 'goats', 'ads' => 16]);
        $this->switch('performance.page_cache', true);
        $this->assertStringContainsString('data-popular-searches', $this->home());
        $this->get('/')->assertHeader('X-Page-Cache', 'HIT');

        $this->asStaff();
        $this->putJson('/admin/api/features/home.popular_searches', ['enabled' => false])->assertOk();
        // The activity log names the switch (it said "Switched “” OFF" for every key with a dot before PR 86).
        $this->assertDatabaseHas('admin_activities', ['action' => 'feature.off', 'summary' => 'Switched “Home: popular searches” OFF']);
        auth()->logout();

        $html = $this->home();
        $this->assertStringNotContainsString('data-popular-searches', $html);
        $this->assertStringContainsString('data-home-promise', $html, 'the other parts stay');
        $this->assertStringContainsString('data-home-post', $html);
    }

    public function test_each_new_switch_hides_only_its_part(): void
    {
        Ad::factory()->live()->count(3)->create(['category_id' => Category::where('slug', 'cars')->value('id')]);
        DB::table('search_terms')->insert(['term' => 'goats', 'ads' => 16]);
        $parts = [
            'home.promise' => 'data-home-promise',
            'home.popular_searches' => 'data-popular-searches',
            'home.post_button' => 'data-home-post',
            'home.quick_links' => 'data-home-quick-links',
            'home.popular_categories' => 'data-home-popular-categories',
            'home.recommended' => 'data-for-you',
        ];
        foreach ($parts as $key => $marker) {
            $this->switch($key, false);
            $html = $this->home();
            $this->assertStringNotContainsString($marker, $html, $key);
            foreach ($parts as $other => $otherMarker) {
                if ($other !== $key) {
                    $this->assertStringContainsString($otherMarker, $html, "{$key} OFF must not hide {$other}");
                }
            }
            $this->assertStringContainsString('id="trending"', $html, 'latest ads always shown');
            $this->switch($key, true);
        }

        // "Recommended" also follows the site-wide switch.
        $this->switch('recommendations', false);
        $this->assertStringNotContainsString('data-for-you', $this->home());
    }

    public function test_only_staff_who_manage_switches_and_switch_off(): void
    {
        $this->getJson('/admin/api/home-page')->assertUnauthorized();
        $this->asStaff('support');
        $this->getJson('/admin/api/home-page')->assertForbidden();

        $this->asStaff();
        $this->getJson('/admin/api/me')->assertOk()->assertJsonPath('user.home_page', true);
        $this->switch('admin.home_page', false);
        $this->getJson('/admin/api/home-page')->assertNotFound();
        $this->getJson('/admin/api/me')->assertJsonPath('user.home_page', false);
    }

    public function test_old_hero_switch_value_is_carried_over(): void
    {
        $migration = require database_path('migrations/2026_11_20_090000_split_home_hero_switch.php');
        $keys = ['home.promise', 'home.popular_searches', 'home.post_button'];

        // Was OFF: the three parts stay hidden.
        DB::table('features')->whereIn('key', $keys)->delete();
        DB::table('features')->insert(['key' => 'home.hero_plus', 'enabled' => false, 'created_at' => now(), 'updated_at' => now()]);
        $migration->up();
        $this->assertSame([false, false, false], array_map(fn ($k) => app(FeatureManager::class)->enabled($k), $keys));
        $this->assertFalse(DB::table('features')->where('key', 'home.hero_plus')->exists());
        $this->assertStringNotContainsString('data-home-promise', $this->home());

        // Back and forth keeps it.
        $migration->down();
        $this->assertSame(0, (int) DB::table('features')->where('key', 'home.hero_plus')->value('enabled'));
        $migration->up();
        $this->assertFalse(app(FeatureManager::class)->enabled('home.popular_searches'));

        // Never changed: nothing stored, defaults (ON).
        DB::table('features')->whereIn('key', [...$keys, 'home.hero_plus'])->delete();
        $migration->up();
        $this->assertSame(0, DB::table('features')->whereIn('key', $keys)->count());
        $this->assertTrue(app(FeatureManager::class)->enabled('home.promise'));
    }
}
