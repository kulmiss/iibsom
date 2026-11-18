<?php

namespace Tests\Feature;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\AdPhoto;
use App\Models\Category;
use App\Models\User;
use App\Support\Cache\PageCache;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** PR 76: a rich ad page (photos, contact bar, details, read more, more from this seller, recently viewed). */
class AdPageRichTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Ad $ad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        foreach (['login.google', 'chat'] as $switch) {
            app(FeatureManager::class)->set($switch, true);
        }
        $this->seller = User::factory()->completed()->create(['name' => 'Cabdi Seller', 'phone' => '+252615001122', 'phone_has_whatsapp' => true]);
        $this->ad = $this->car('Toyota Hilux 2016', ['fields' => ['brand' => 'toyota', 'year' => 2016, 'mileage' => 120000]]);
    }

    /** @param array<string, mixed> $attributes */
    private function car(string $title, array $attributes = []): Ad
    {
        return Ad::factory()->live()->create($attributes + [
            'user_id' => $this->seller->id,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'title' => $title,
            'description' => 'Very clean car.',
            'price' => 18500,
            'published_at' => now(),
        ]);
    }

    private function photos(int $n): void
    {
        foreach (range(1, $n) as $i) {
            AdPhoto::factory()->create(['ad_id' => $this->ad->id, 'user_id' => $this->seller->id, 'sort' => $i]);
        }
        PageCache::bump();
    }

    private function switch(string $key, bool $on): void
    {
        app(FeatureManager::class)->set($key, $on);
        PageCache::bump();
    }

    private function page(string $prefix = ''): string
    {
        return (string) $this->get($prefix.$this->ad->fresh()?->url())->assertOk()->getContent();
    }

    // ---- Photos ----

    public function test_small_pictures_and_a_full_screen_viewer_with_every_photo(): void
    {
        $this->photos(3);
        $html = $this->page();

        $this->assertSame(3, substr_count($html, 'data-thumb="'));
        $this->assertSame(3, substr_count($html, 'data-src="'));
        $this->assertStringContainsString('data-viewer', $html);
        $this->assertStringContainsString('aria-label="Next photo"', $html);
        $this->assertStringNotContainsString('data-zoom-dialog', $html);
    }

    public function test_one_photo_has_no_small_pictures_and_no_photo_shows_a_calm_box(): void
    {
        $this->assertStringContainsString('No photo', $this->page());

        $this->photos(1);
        $html = $this->page();
        $this->assertStringNotContainsString('data-thumbs', $html);
        $this->assertStringNotContainsString('data-viewer-next', $html);
    }

    public function test_gallery_switch_off_keeps_the_old_zoom(): void
    {
        $this->photos(3);
        $this->switch('ads.gallery_plus', false);
        $html = $this->page();
        $this->assertStringNotContainsString('data-thumbs', $html);
        $this->assertStringNotContainsString('data-viewer', $html);
        $this->assertStringContainsString('data-zoom-dialog', $html);
    }

    // ---- Details and text ----

    public function test_key_details_have_a_picture_each(): void
    {
        $html = $this->page();
        $start = (int) strpos($html, 'data-details');
        $grid = substr($html, $start, (int) strpos($html, '</dl>', $start) - $start);

        $this->assertSame(substr_count($grid, '<dt'), substr_count($grid, '<svg'));
        $this->assertGreaterThanOrEqual(3, substr_count($grid, '<svg')); // brand, year, kilometres
        $this->assertStringContainsString('120,000 km', $grid);
        // Different pictures, not the same tick everywhere.
        preg_match_all('/<path d="([^"]+)"/', $grid, $paths);
        $this->assertGreaterThanOrEqual(3, count(array_unique($paths[1])));
    }

    public function test_read_more_button_is_ready_and_the_full_text_stays_in_the_page(): void
    {
        $long = str_repeat('A clean car with new tyres and a good engine. ', 40);
        $this->ad->forceFill(['description' => trim($long)])->save();
        PageCache::bump();

        $html = $this->page();
        $this->assertStringContainsString('data-read-more-button hidden', $html);
        $this->assertStringContainsString(trim($long), $html);
        $this->get('/so'.parse_url($this->ad->url(), PHP_URL_PATH))->assertOk()->assertSee(__('ads.page.read_more', [], 'so'));

        $this->switch('ads.read_more', false);
        $this->assertStringNotContainsString('data-read-more', $this->page());
    }

    // ---- Phone contact bar ----

    public function test_contact_bar_for_buyers_never_holds_the_number(): void
    {
        $html = $this->page();
        $this->assertStringContainsString('data-contact-bar', $html);
        foreach (['call', 'whatsapp', 'chat'] as $kind) {
            $this->assertStringContainsString('data-bar="'.$kind.'"', $html);
        }
        $this->assertStringContainsString('$18,500', substr($html, (int) strpos($html, 'data-contact-bar'), 600));
        $this->assertStringNotContainsString('615001122', $html); // still only after a tap
        $this->assertStringContainsString('id="contact"', $html);  // without JavaScript the buttons jump here
    }

    public function test_signed_in_buyers_get_the_offer_button_in_the_bar(): void
    {
        $buyer = User::factory()->completed()->create();
        $this->actingAs($buyer)->get($this->ad->url())->assertOk()->assertSee('data-bar="offer"', false)->assertSee('id="offer"', false);
    }

    public function test_no_contact_bar_on_your_own_ad_on_ended_ads_or_with_the_switch_off(): void
    {
        $this->actingAs($this->seller)->get($this->ad->url())->assertOk()->assertDontSee('data-contact-bar', false);
        auth()->logout();

        $this->switch('ads.sticky_contact', false);
        $this->assertStringNotContainsString('data-contact-bar', $this->page());
        $this->switch('ads.sticky_contact', true);

        $this->ad->forceFill(['status' => AdStatus::Sold])->save();
        PageCache::bump();
        $this->actingAs($this->seller)->get($this->ad->url())->assertOk()->assertDontSee('data-contact-bar', false);
    }

    // ---- More from this seller, recently viewed, follow ----

    public function test_more_from_this_seller_shows_only_their_other_live_ads(): void
    {
        $other = $this->car('Mazda Demio 2011 from Cabdi');
        $this->car('Waiting Nissan', ['status' => AdStatus::Pending]);
        Ad::factory()->live()->create(['title' => 'Someone else Suzuki', 'category_id' => Category::where('slug', 'cars')->value('id')]);

        $html = $this->page();
        $start = strpos($html, 'data-more-from-seller');
        $this->assertNotFalse($start);
        $box = substr($html, (int) $start, 6000);
        $this->assertStringContainsString('More from Cabdi Seller', $box);
        $this->assertStringContainsString($other->title, $box);
        $this->assertStringNotContainsString('Waiting Nissan', $box);
        $this->assertStringNotContainsString('Someone else Suzuki', $box);
        $this->assertStringNotContainsString('>Toyota Hilux 2016<', $box);

        $this->switch('ads.more_from_seller', false);
        $this->assertStringNotContainsString('data-more-from-seller', $this->page());
    }

    public function test_the_seller_ad_count_is_right_with_few_and_many_ads(): void
    {
        $this->car('Second car');
        $this->assertStringContainsString(trans_choice('ads.page.seller_ads', 2, ['count' => 2]), $this->page());

        foreach (range(1, 7) as $i) {
            $this->car('More car '.$i);
        }
        PageCache::bump();
        $this->assertStringContainsString(trans_choice('ads.page.seller_ads', 9, ['count' => 9]), $this->page());
    }

    public function test_recently_viewed_is_filled_from_the_browser_and_leaves_this_ad_out(): void
    {
        $this->assertStringContainsString('data-exclude="'.$this->ad->id.'"', $this->page());

        $this->switch('ads.recently_viewed', false);
        $this->assertStringNotContainsString('data-exclude=', $this->page());
        $this->switch('ads.recently_viewed', true);
        $this->switch('recommendations', false);
        $this->assertStringNotContainsString('data-exclude=', $this->page());
    }

    public function test_follow_the_seller_from_the_ad_page(): void
    {
        $buyer = User::factory()->completed()->create();
        $this->actingAs($buyer)->get($this->ad->url())->assertSee('data-follow', false);
        $this->actingAs($buyer)->post(lroute('follow.toggle', ['seller' => $this->seller->id]))->assertRedirect();
        $this->actingAs($buyer)->get($this->ad->url())->assertSee('aria-pressed="true"', false);

        $this->actingAs($this->seller)->get($this->ad->url())->assertDontSee('data-follow', false);
    }

    // ---- Still cached for guests ----

    public function test_guest_ad_page_stays_cached_and_cookie_free(): void
    {
        $response = $this->get($this->ad->url())->assertOk();
        $this->assertSame([], $response->headers->getCookies());
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
    }
}
