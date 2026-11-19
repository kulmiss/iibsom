<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Support\Cache\PageCache;
use App\Support\Catalog;
use App\Support\Features\FeatureManager;
use App\Support\Routing\ReservedSlugs;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** PR 84: the "All categories" page (switch "catalog.finder"). */
class CategoryFinderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
    }

    private function switch(string $key, bool $on): void
    {
        app(FeatureManager::class)->set($key, $on);
        PageCache::bump();
    }

    private function liveCar(): Ad
    {
        $ad = Ad::factory()->live()->create(['category_id' => Category::where('slug', 'cars')->value('id')]);
        app(Catalog::class)->flush(); // counts are cached

        return $ad;
    }

    public function test_lists_every_main_and_sub_category_with_links_and_counts(): void
    {
        $this->liveCar();
        $html = (string) $this->get('/categories')->assertOk()->getContent();

        foreach (Category::query()->topLevel()->enabled()->get() as $main) {
            $this->assertStringContainsString('href="'.e(lroute('browse.category', ['category' => $main->slug])).'"', $html, $main->slug);
        }
        $cars = Category::where('slug', 'cars')->firstOrFail();
        $this->assertGreaterThan(0, $cars->fresh()->ads_count);
        $this->assertStringContainsString('href="'.e(lroute('browse.category', ['category' => 'cars'])).'"', $html);
        $this->assertStringContainsString(trans_choice('site.ads_count', $cars->ads_count, ['count' => $cars->ads_count]), $html);
        $this->assertStringContainsString('data-finder-search', $html);
        $this->assertStringContainsString('<h1 class="mt-2 text-2xl font-bold">All categories</h1>', $html);
    }

    public function test_search_names_hold_english_and_somali_so_either_word_finds_it(): void
    {
        $cars = Category::where('slug', 'cars')->firstOrFail();
        $html = (string) $this->get('/categories')->assertOk()->getContent();

        $names = e(mb_strtolower($cars->name['en'].' '.$cars->name['so']));
        $this->assertStringContainsString('data-names="'.$names.'"', $html);
    }

    public function test_somali_page_uses_somali_words_and_so_links(): void
    {
        $html = (string) $this->get('/so/categories')->assertOk()->getContent();

        $this->assertStringContainsString('Dhammaan qaybaha', $html);
        $this->assertStringContainsString('href="'.e(url('/so/cars')).'"', $html);
        $this->assertStringNotContainsString('ads.finder.', $html); // no missing keys
    }

    public function test_empty_sub_categories_follow_the_hide_empty_switch(): void
    {
        $this->liveCar();
        $empty = Category::where('slug', 'camels')->firstOrFail();
        $this->assertSame(0, (int) $empty->ads_count);

        $this->switch('catalog.hide_empty', false);
        $this->assertStringContainsString(e(lroute('browse.category', ['category' => 'camels'])).'"', (string) $this->get('/categories')->getContent());

        $this->switch('catalog.hide_empty', true);
        app(Catalog::class)->flush();
        $html = (string) $this->get('/categories')->getContent();
        $this->assertStringNotContainsString(e(lroute('browse.category', ['category' => 'camels'])).'"', $html);
        $this->assertStringContainsString(e(lroute('browse.category', ['category' => 'cars'])).'"', $html);
    }

    public function test_home_links_to_it_and_switch_off_hides_link_and_page(): void
    {
        $home = (string) $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-finder-tile', $home);
        $this->assertStringContainsString('data-finder-link', $home);
        $this->assertStringContainsString('href="'.e(lroute('categories')).'"', $home);

        $this->switch('catalog.finder', false);
        $this->get('/categories')->assertNotFound();
        $this->get('/so/categories')->assertNotFound();
        $home = (string) $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-finder-tile', $home);
        $this->assertStringNotContainsString('data-finder-link', $home);
    }

    public function test_cached_and_cookie_free_for_guests(): void
    {
        $this->switch('performance.page_cache', true);
        $first = $this->get('/categories')->assertOk()->assertHeader('X-Page-Cache', 'MISS');
        $this->assertSame([], $first->headers->getCookies());
        $this->get('/categories')->assertOk()->assertHeader('X-Page-Cache', 'HIT');
    }

    public function test_categories_is_a_reserved_slug(): void
    {
        $this->assertTrue(ReservedSlugs::has('categories'));
        $this->assertNull(Category::where('slug', 'categories')->first());
    }
}
