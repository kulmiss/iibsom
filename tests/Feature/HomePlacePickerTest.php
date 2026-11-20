<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Area;
use App\Models\Category;
use App\Models\City;
use App\Support\Cache\PageCache;
use App\Support\Catalog;
use App\Support\Features\FeatureManager;
use App\Support\Home\HomeSections;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** PR 87: the place picker next to the home search, and the category list that stays in view. */
class HomePlacePickerTest extends TestCase
{
    use RefreshDatabase;

    private City $mogadishu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->mogadishu = City::where('slug', 'mogadishu')->firstOrFail();
    }

    private function switch(string $key, bool $on): void
    {
        app(FeatureManager::class)->set($key, $on);
        PageCache::bump();
    }

    private function ad(City $city, ?Area $area = null): Ad
    {
        $ad = Ad::factory()->live()->create([
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => $city->id, 'area_id' => $area?->id,
        ]);
        app(Catalog::class)->flush();
        PageCache::bump();

        return $ad;
    }

    /** @return array{0: Area, 1: Area} two districts named so that A–Z order is clear */
    private function districts(): array
    {
        $areas = Area::where('city_id', $this->mogadishu->id)->where('enabled', true)->orderBy('id')->take(2)->get();
        $areas[0]->forceFill(['name' => ['en' => 'Zeta District', 'so' => 'Degmada Zeta']])->save();
        $areas[1]->forceFill(['name' => ['en' => 'Alpha District', 'so' => 'Degmada Alpha']])->save();

        return [$areas[0], $areas[1]];
    }

    private function onlyMogadishuLive(): void
    {
        City::where('id', '!=', $this->mogadishu->id)->update(['enabled' => false]);
        app(Catalog::class)->flush();
    }

    public function test_one_city_with_districts_opens_its_districts_a_to_z_with_counts(): void
    {
        $this->switch('catalog.hide_empty', true);
        $this->onlyMogadishuLive();
        [$zeta, $alpha] = $this->districts();
        $this->ad($this->mogadishu, $zeta);
        $this->ad($this->mogadishu, $zeta);
        $this->ad($this->mogadishu, $alpha);

        $html = (string) $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-loc-open', $html);
        $this->assertStringContainsString('data-loc-start="mogadishu"', $html);

        $list = HomeSections::locations();
        $this->assertCount(1, $list);
        $this->assertSame(['Alpha District', 'Zeta District'], array_column($list[0]['areas'], 'label'));
        $this->assertSame([1, 2], array_column($list[0]['areas'], 'count'));
        $this->assertStringContainsString('data-city="mogadishu" data-area="'.$zeta->id.'" data-label="Zeta District, Mogadishu"', $html);
        $this->assertStringContainsString('2 ads', $html);
        $this->assertStringContainsString('data-loc-names="zeta district mogadishu"', $html);
    }

    public function test_choices_lead_to_results_with_that_place(): void
    {
        $this->onlyMogadishuLive();
        [$zeta, $alpha] = $this->districts();
        $inZeta = $this->ad($this->mogadishu, $zeta);
        $inAlpha = $this->ad($this->mogadishu, $alpha);
        $inZeta->update(['title' => 'Zeta car for sale']);
        $inAlpha->update(['title' => 'Alpha car for sale']);
        PageCache::bump();

        // What the form sends after a district is picked.
        $this->get('/search?city=mogadishu&area='.$zeta->id)->assertOk()->assertSee('Zeta car for sale')->assertDontSee('Alpha car for sale');
        $this->get('/search?city=mogadishu')->assertOk()->assertSee('Zeta car for sale')->assertSee('Alpha car for sale');
    }

    public function test_the_simple_city_list_stays_for_phones_without_javascript(): void
    {
        $other = City::where('id', '!=', $this->mogadishu->id)->firstOrFail();
        $other->update(['enabled' => true]);
        app(Catalog::class)->flush();
        $this->ad($this->mogadishu);
        $this->ad($other);

        $html = (string) $this->get('/')->assertOk()->getContent();
        // Picker fields are not sent until JavaScript turns them on after a choice.
        $this->assertStringContainsString('<input type="hidden" name="city" value="" disabled data-loc-city>', $html);
        $this->assertStringContainsString('<input type="hidden" name="area" value="" disabled data-loc-area>', $html);
        $this->assertMatchesRegularExpression('/<select id="city" name="city" class="[^"]*\[\.js_&\]:hidden"\s+data-loc-fallback\s*>/', $html);
        $this->assertStringNotContainsString('data-loc-start=', $html, 'two cities: start at the city list');
    }

    public function test_no_districts_while_more_filters_is_off_and_no_picker_without_a_choice(): void
    {
        $this->onlyMogadishuLive();
        [$zeta, $alpha] = $this->districts();
        $this->ad($this->mogadishu, $zeta);
        $this->ad($this->mogadishu, $alpha);

        $this->switch('search.more_filters', false); // the search could not use a district
        $this->assertSame([], HomeSections::locations());
        $html = (string) $this->get('/')->getContent();
        $this->assertStringNotContainsString('data-loc-open', $html);
        $this->assertStringNotContainsString('place-picker', $html);
        $this->assertStringNotContainsString('<select id="city"', $html);
    }

    public function test_empty_places_follow_hide_empty(): void
    {
        $this->onlyMogadishuLive();
        [$zeta, $alpha] = $this->districts();
        $this->ad($this->mogadishu, $zeta);
        $this->ad($this->mogadishu, $alpha);
        $enabled = Area::where('city_id', $this->mogadishu->id)->where('enabled', true)->count();
        $this->assertGreaterThan(2, $enabled);

        $this->switch('catalog.hide_empty', true);
        $this->assertCount(2, HomeSections::locations()[0]['areas']);
        $this->switch('catalog.hide_empty', false);
        $this->assertCount($enabled, HomeSections::locations()[0]['areas']);
    }

    public function test_somali_words_and_switch_off(): void
    {
        $this->onlyMogadishuLive();
        [$zeta, $alpha] = $this->districts();
        $this->ad($this->mogadishu, $zeta);
        $this->ad($this->mogadishu, $alpha);

        $html = (string) $this->get('/so')->assertOk()->getContent();
        $this->assertStringContainsString('Dooro goob', $html);
        $this->assertStringContainsString('Degmada Alpha', $html);
        $this->assertStringNotContainsString('site.home.place_', $html);

        $this->switch('search.location_picker', false);
        $this->assertStringNotContainsString('data-loc-open', (string) $this->get('/')->getContent());
    }

    public function test_query_budget_holds_and_the_list_is_cached(): void
    {
        $this->onlyMogadishuLive();
        [$zeta, $alpha] = $this->districts();
        $this->ad($this->mogadishu, $zeta);
        $this->ad($this->mogadishu, $alpha);
        HomeSections::locations();

        \DB::flushQueryLog();
        \DB::enableQueryLog();
        HomeSections::locations();
        $n = collect(\DB::getQueryLog())->reject(fn ($q) => str_contains($q['query'], '`cache`'))->count();
        \DB::disableQueryLog();
        $this->assertSame(0, $n, 'second call comes from the cache');
    }

    // ---- Category list stays in view (computers) ----

    public function test_category_list_is_sticky_and_scrolls_inside_with_its_switch(): void
    {
        $html = (string) $this->get('/')->getContent();
        $this->assertMatchesRegularExpression('/<aside class="hidden lg:sticky lg:top-20 lg:flex lg:max-h-\[calc\(100dvh-6rem\)\] lg:flex-col lg:self-start"[^>]*data-sticky\s*>/', $html);
        $this->assertStringContainsString('min-h-0 flex-1 overflow-y-auto', $html);

        $this->switch('home.sticky_categories', false);
        $html = (string) $this->get('/')->getContent();
        $this->assertStringNotContainsString('data-sticky', $html);
        $this->assertStringContainsString('<aside class="hidden relative lg:block"', $html);
    }
}
