<?php

namespace Tests\Feature;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\User;
use App\Support\Cache\PageCache;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** PR 82: "Post an ad like this", brand/model links, "Already sold?", and "Show all details". */
class AdPageExtrasTest extends TestCase
{
    use RefreshDatabase;

    private Ad $ad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->ad = $this->car();
    }

    /** @param array<string, mixed> $fields */
    private function car(array $fields = [], AdStatus $status = AdStatus::Live): Ad
    {
        return Ad::factory()->create([
            'status' => $status, 'published_at' => $status === AdStatus::Live ? now() : null,
            'title' => 'Toyota Vitz 2012 clean', 'condition' => 'used',
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'fields' => $fields + ['brand' => 'toyota', 'model' => 'Vitz', 'year' => 2012, 'fuel' => 'petrol'],
        ]);
    }

    private function switch(string $key, bool $on): void
    {
        app(FeatureManager::class)->set($key, $on);
        PageCache::bump();
    }

    private function page(?Ad $ad = null): string
    {
        return (string) $this->get(($ad ?? $this->ad)->url())->assertOk()->getContent();
    }

    // ---- Post an ad like this ----

    public function test_post_like_this_fills_condition_and_list_choices_only(): void
    {
        $this->assertStringContainsString(e(lroute('post.form', ['category' => 'cars', 'like' => $this->ad->id])), $this->page());

        $seller = User::factory()->completed()->create();
        $html = (string) $this->actingAs($seller)->get(lroute('post.form', ['category' => 'cars', 'like' => $this->ad->id]))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<option value="toyota"\s+selected/', $html);   // brand (a list)
        $this->assertMatchesRegularExpression('/<option value="petrol"\s+selected/', $html);   // fuel (a list)
        $this->assertMatchesRegularExpression('/value="used"[^>]*checked/', $html);             // condition
        $this->assertStringNotContainsString('value="Vitz"', $html);                             // typed text: never copied
        $this->assertStringNotContainsString('Toyota Vitz 2012 clean', $html);                   // title: never copied
    }

    public function test_post_like_this_ignores_other_categories_and_ads_that_are_not_live(): void
    {
        $seller = User::factory()->completed()->create();
        $waiting = $this->car([], AdStatus::Pending);

        foreach ([lroute('post.form', ['category' => 'cars', 'like' => $waiting->id]), lroute('post.form', ['category' => 'mobile-phones', 'like' => $this->ad->id]), lroute('post.form', ['category' => 'cars', 'like' => 'abc'])] as $url) {
            $html = (string) $this->actingAs($seller)->get($url)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/<option value="toyota"\s+selected/', $html, $url);
        }
    }

    public function test_post_like_this_is_not_offered_on_your_own_ad_or_with_the_switch_off(): void
    {
        $this->actingAs($this->ad->user)->get($this->ad->url())->assertDontSee('data-post-like-this', false);
        auth()->logout();

        $this->switch('ads.post_like_this', false);
        $this->assertStringNotContainsString('data-post-like-this', $this->page());

        $seller = User::factory()->completed()->create();
        $html = (string) $this->actingAs($seller)->get(lroute('post.form', ['category' => 'cars', 'like' => $this->ad->id]))->getContent();
        $this->assertDoesNotMatchRegularExpression('/<option value="toyota"\s+selected/', $html);
    }

    // ---- Brand and model links ----

    public function test_list_choices_that_can_be_filtered_are_links(): void
    {
        $html = $this->page();
        $brand = e(lroute('browse.category', ['category' => 'cars']).'?'.http_build_query(['f' => ['brand' => 'toyota']]));
        $this->assertStringContainsString('href="'.$brand.'"', $html);
        $this->assertStringNotContainsString('f%5Bmodel%5D', $html); // model is typed text here, not a list
        $this->assertStringNotContainsString('f%5Byear%5D', $html);  // numbers are not links

        // The link really lists the ad.
        $this->get(lroute('browse.category', ['category' => 'cars']).'?'.http_build_query(['f' => ['brand' => 'toyota']]))->assertSee('Toyota Vitz 2012 clean');

        $this->switch('ads.field_links', false);
        $this->assertStringNotContainsString('data-field-link', $this->page());
    }

    // ---- Already sold? ----

    public function test_already_sold_link_for_guests_and_signed_in_people(): void
    {
        $this->assertStringContainsString('data-mark-sold', $this->page());
        $this->get('/so'.parse_url($this->ad->url(), PHP_URL_PATH))->assertSee(__('ads.page.mark_sold', [], 'so'));

        $buyer = User::factory()->completed()->create();
        $this->actingAs($buyer)->get($this->ad->url())->assertSee('data-mark-sold', false)->assertSee('value="sold"', false);
    }

    public function test_already_sold_link_hides_when_reports_are_off_or_already_reported(): void
    {
        $this->switch('ads.guest_reports', false);
        $this->assertStringNotContainsString('data-mark-sold', $this->page());

        $buyer = User::factory()->completed()->create();
        $this->actingAs($buyer)->post(lroute('ad.report', ['ad' => $this->ad->id]), ['reason' => 'sold']);
        $this->actingAs($buyer)->get($this->ad->url())->assertDontSee('data-mark-sold', false);

        auth()->logout();
        $this->switch('ads.guest_reports', true);
        $this->switch('ads.mark_unavailable', false);
        $this->assertStringNotContainsString('data-mark-sold', $this->page());
    }

    // ---- Show all details ----

    public function test_long_details_fold_after_six_and_short_ones_do_not(): void
    {
        $this->assertStringNotContainsString('data-details-more', $this->page()); // condition + 4 = 5

        $long = $this->car(['mileage' => 90000, 'transmission' => 'automatic', 'colour' => 'white', 'body_type' => 'hatchback']);
        $html = $this->page($long); // condition + 8 = 9
        $this->assertStringContainsString('data-details-more', $html);
        $this->assertSame(3, substr_count($html, 'data-detail-extra'));
        $this->assertStringContainsString('Show all 9 details', $html);
        // Without JavaScript nothing is hidden: the rows hide only under html.js.
        $this->assertMatchesRegularExpression('/data-detail-extra|\[\.js_&(amp;)?\]:hidden/', $html);
        $this->assertSame(3, preg_match_all('/class="relative min-h-9 ps-11 \[\.js_&(amp;)?\]:hidden"/', $html));

        $this->switch('ads.details_more', false);
        $this->assertStringNotContainsString('data-detail-extra', $this->page($long));
    }

    public function test_guest_ad_page_stays_cached(): void
    {
        $response = $this->get($this->ad->url())->assertOk();
        $this->assertSame([], $response->headers->getCookies());
    }
}
