<?php

namespace Tests\Feature;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Area;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Cache\PageCache;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** PR 84: the phone top bar on the ad page, "Discover more" and the number on the Saved icon. */
class AdPhoneBarDiscoverTest extends TestCase
{
    use RefreshDatabase;

    private Ad $ad;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $city = City::where('slug', 'mogadishu')->firstOrFail();
        $this->area = Area::where('city_id', $city->id)->where('enabled', true)->firstOrFail();
        $this->ad = Ad::factory()->live()->create([
            'title' => 'Toyota Vitz 2012 clean', 'condition' => 'used',
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => $city->id, 'area_id' => $this->area->id,
            'fields' => ['brand' => 'toyota', 'model' => 'Vitz', 'year' => 2012],
        ]);
    }

    private function switch(string $key, bool $on): void
    {
        app(FeatureManager::class)->set($key, $on);
        PageCache::bump();
    }

    private function page(?string $url = null): string
    {
        return (string) $this->get($url ?? $this->ad->url())->assertOk()->getContent();
    }

    // ---- Phone top bar ----

    public function test_ad_page_has_the_phone_bar_with_back_share_and_language(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('data-phone-bar', $html);
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(e(lroute('browse.category', ['category' => 'cars'])), '/').'" class="icon-btn shrink-0" aria-label="Back to Cars" data-phone-back>/', $html);
        $this->assertStringContainsString('data-share-sheet data-url="'.e($this->ad->url()).'"', $html);
        $this->assertStringContainsString('aria-label="Share"', $html);
        $this->assertSame(2, substr_count($html, 'data-lang-menu'), 'language menu in the phone bar and the normal bar');
        $this->assertStringContainsString('class="mx-auto h-14 max-w-6xl items-center gap-2 px-4 sm:gap-3 lg:h-auto lg:py-2.5 hidden lg:flex"', $html);
    }

    public function test_phone_bar_has_the_same_fixed_height_as_the_normal_bar(): void
    {
        // PR 100 (owner's recording): on the ad page the bar was 48 px, elsewhere 56 px, so it jumped between pages.
        $this->assertStringContainsString('<div class="flex h-14 items-center gap-1 px-2 lg:hidden" data-phone-bar>', $this->page());
        $this->assertMatchesRegularExpression('#<div class="mx-auto h-14 max-w-6xl[^"]*\bflex"\s*data-main-bar>#', (string) $this->get('/cars')->getContent());
    }

    public function test_somali_phone_bar(): void
    {
        $html = $this->page(str_replace(url('/'), url('/so'), $this->ad->url()));
        $cars = Category::where('slug', 'cars')->firstOrFail();
        $this->assertStringContainsString('aria-label="Ku noqo '.e($cars->name['so']).'"', $html);
        $this->assertStringContainsString('aria-label="La wadaag"', $html);
    }

    public function test_other_pages_and_switch_off_keep_the_normal_bar(): void
    {
        foreach (['/', '/cars', '/categories'] as $url) {
            $html = $this->page($url);
            $this->assertStringNotContainsString('data-phone-bar', $html, $url);
            $this->assertStringContainsString('class="mx-auto h-14 max-w-6xl items-center gap-2 px-4 sm:gap-3 lg:h-auto lg:py-2.5 flex"', $html, $url);
        }

        $this->switch('ads.phone_top_bar', false);
        $html = $this->page();
        $this->assertStringNotContainsString('data-phone-bar', $html);
        $this->assertStringNotContainsString('data-share-sheet', $html);
    }

    public function test_save_in_the_phone_bar_when_favourites_is_on(): void
    {
        $this->assertStringNotContainsString('data-favourite', $this->page()); // favourites OFF by default

        $this->switch('favourites', true);
        $buyer = User::factory()->completed()->create();
        $html = (string) $this->actingAs($buyer)->get($this->ad->url())->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'data-favourite '), 'one in the phone bar, one by the title');
        $this->assertStringContainsString('icon-btn aria-pressed:bg-white/20', $html);

        // Saving from either one saves the same ad.
        $this->actingAs($buyer)->postJson(lroute('favourite.toggle', ['ad' => $this->ad->id]))->assertOk()->assertJson(['saved' => true]);
        $html = (string) $this->actingAs($buyer)->get($this->ad->url())->getContent();
        $this->assertSame(2, substr_count($html, 'aria-pressed="true"'));
    }

    // ---- Discover more ----

    public function test_discover_links_come_from_the_ad_and_each_one_finds_it(): void
    {
        $html = $this->page();
        $this->assertStringContainsString('data-discover', $html);

        preg_match('/<nav class="mt-8" aria-labelledby="discover-title" data-discover>(.*?)<\/nav>/s', $html, $m);
        preg_match_all('/<a href="([^"]+)"[^>]*>.*?<\/svg>([^<]+)<\/a>/s', $m[1] ?? '', $links, PREG_SET_ORDER);
        $labels = array_map(fn ($l) => trim(html_entity_decode($l[2])), $links);
        $this->assertSame(['Used Cars', 'Toyota Cars', 'Cars in Mogadishu', 'Cars in '.$this->area->label, 'All in Vehicles'], $labels);

        foreach ($links as $link) {
            $url = html_entity_decode($link[1]);
            $this->get($url)->assertOk()->assertSee('Toyota Vitz 2012 clean');
        }
    }

    public function test_discover_leaves_out_what_the_ad_does_not_have(): void
    {
        $plain = Ad::factory()->live()->create([
            'title' => 'Plain ad', 'condition' => null, 'area_id' => null,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'), 'fields' => ['brand' => 'not-a-brand'],
        ]);
        $html = $this->page($plain->url());
        preg_match('/data-discover>(.*?)<\/nav>/s', $html, $m);
        $box = $m[1] ?? '';
        $this->assertStringNotContainsString('?condition=', $box);
        $this->assertStringNotContainsString('f%5Bbrand', $box); // not a real brand choice
        $this->assertStringNotContainsString('?area=', $box);
        $this->assertStringContainsString('Cars in Mogadishu', $box);
        $this->assertStringContainsString('All in Vehicles', $box);
    }

    public function test_area_link_needs_more_filters(): void
    {
        $this->assertStringContainsString('?area='.$this->area->id, $this->page()); // ON by default
        $this->switch('search.more_filters', false);
        $this->assertStringNotContainsString('?area=', $this->page());
    }

    public function test_discover_in_somali(): void
    {
        $html = $this->page(str_replace(url('/'), url('/so'), $this->ad->url()));
        $this->assertStringContainsString('Wax kale ka eeg', $html);
        $this->assertStringContainsString('href="'.e(url('/so/cars').'?condition=used').'"', $html);
        $this->assertStringContainsString(' la isticmaalay', $html);
        $this->assertStringNotContainsString('ads.discover.', $html);
    }

    public function test_no_discover_with_switch_off_or_a_sold_ad(): void
    {
        $this->switch('ads.discover_more', false);
        $this->assertStringNotContainsString('data-discover', $this->page());

        $this->switch('ads.discover_more', true);
        $this->ad->forceFill(['status' => AdStatus::Sold])->save();
        PageCache::bump();
        $this->assertStringNotContainsString('data-discover', $this->page());
    }

    // ---- Number on the Saved icon ----

    public function test_saved_icons_show_how_many_ads_you_saved(): void
    {
        $this->switch('favourites', true);
        $user = User::factory()->completed()->create();
        $others = Ad::factory()->live()->count(2)->create();
        foreach ($others as $a) {
            DB::table('favourites')->insert(['user_id' => $user->id, 'ad_id' => $a->id, 'created_at' => now(), 'updated_at' => now()]);
        }

        $html = (string) $this->actingAs($user)->get('/')->assertOk()->getContent();
        $this->assertSame(2, preg_match_all('/data-saved-count="2">2<\/span>/', $html), 'top bar and bottom bar');

        // None saved: kept in the page but hidden, so a Save can show it at once.
        $fresh = User::factory()->completed()->create();
        $html = (string) $this->actingAs($fresh)->get('/')->getContent();
        $this->assertSame(2, preg_match_all('/hidden" data-saved-count="0">0</', $html));
    }

    public function test_no_saved_number_for_guests_or_with_the_switches_off(): void
    {
        $this->switch('favourites', true);
        $this->assertStringNotContainsString('data-saved-count', $this->page('/'));

        $user = User::factory()->completed()->create();
        $this->switch('account.saved_count', false);
        $this->assertStringNotContainsString('data-saved-count', (string) $this->actingAs($user)->get('/')->getContent());

        $this->switch('account.saved_count', true);
        $this->switch('favourites', false);
        $this->assertStringNotContainsString('data-saved-count', (string) $this->actingAs($user)->get('/')->getContent());
    }
}
