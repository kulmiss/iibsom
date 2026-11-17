<?php

namespace Tests\Feature;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Report;
use App\Models\User;
use App\Support\Cache\PageCache;
use App\Support\Features\FeatureManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdPageTest extends TestCase
{
    use RefreshDatabase;

    private Ad $ad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $seller = User::factory()->completed()->create(['name' => 'Cabdi Seller', 'phone' => '+252615001122', 'phone_has_whatsapp' => true]);
        $this->ad = Ad::factory()->live()->create([
            'user_id' => $seller->id,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'title' => 'Toyota Hilux 2016',
            'slug' => 'toyota-hilux-2016',
            'description' => "Very clean.\nNew tyres. <b>bold?</b>",
            'price' => 18500,
            'fields' => ['brand' => 'toyota', 'year' => 2016, 'mileage' => 120000],
        ]);
    }

    private function url(): string
    {
        return '/cars/mogadishu/toyota-hilux-2016-'.$this->ad->id;
    }

    public function test_ad_page_shows_everything(): void
    {
        $this->get($this->url())->assertOk()
            ->assertSee('Toyota Hilux 2016')->assertSee('$18,500')
            ->assertSee('Toyota')->assertSee('120,000 km')->assertSee('2016')
            ->assertSee('Cabdi Seller')->assertSee('Show phone number')
            ->assertSee('Stay safe')->assertSee('report-guest', false) // guests can report (PR 49)
            ->assertSee('&lt;b&gt;bold?&lt;/b&gt;', false);
    }

    public function test_phone_number_is_not_in_the_page(): void
    {
        $this->get($this->url())->assertDontSee('615001122')->assertDontSee('+252 61 500 1122');
    }

    public function test_contact_endpoint_gives_number_and_links(): void
    {
        $this->getJson("/ads/{$this->ad->id}/contact")->assertOk()
            ->assertJsonPath('display', '+252 61 500 1122')
            ->assertJsonPath('call', 'tel:+252615001122')
            ->assertJsonPath('whatsapp', fn ($u) => str_starts_with($u, 'https://wa.me/252615001122?text='))
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_contact_is_rate_limited(): void
    {
        foreach (range(1, 30) as $i) {
            $this->getJson("/ads/{$this->ad->id}/contact");
        }
        $this->getJson("/ads/{$this->ad->id}/contact")->assertStatus(429);
    }

    public function test_whatsapp_and_call_switches(): void
    {
        app(FeatureManager::class)->set('contact.whatsapp', false);
        $this->getJson("/ads/{$this->ad->id}/contact")->assertJsonPath('whatsapp', null);

        app(FeatureManager::class)->set('contact.call', false);
        $this->getJson("/ads/{$this->ad->id}/contact")->assertNotFound();
        $this->get($this->url())->assertDontSee('Show phone number');
    }

    public function test_wrong_slug_redirects_to_clean_url(): void
    {
        $this->get('/vehicles/mogadishu/old-title-'.$this->ad->id)->assertRedirect(url($this->url()))->assertStatus(301);
    }

    public function test_pending_ad_only_visible_to_seller_and_staff(): void
    {
        $this->ad->forceFill(['status' => AdStatus::Pending])->save();

        $this->get($this->url())->assertNotFound();
        $this->actingAs($this->ad->user)->get($this->url())->assertOk()->assertSee('Only you can see this ad')->assertSee('noindex', false);
        $this->getJson("/ads/{$this->ad->id}/contact")->assertNotFound();
    }

    public function test_structured_data_and_social_tags(): void
    {
        $html = $this->get($this->url())->getContent();

        $this->assertStringContainsString('"@type":"Product"', $html);
        $this->assertStringContainsString('"priceCurrency":"USD"', $html);
        $this->assertStringContainsString('property="og:title"', $html);
    }

    public function test_report_needs_sign_in_and_is_saved_once(): void
    {
        $this->post("/ads/{$this->ad->id}/report", ['reason' => 'scam'])->assertRedirect(url('/login'));

        $buyer = User::factory()->completed()->create();
        $this->actingAs($buyer)->post("/ads/{$this->ad->id}/report", ['reason' => 'scam', 'details' => 'Asked for a deposit'])->assertRedirect();
        $this->actingAs($buyer)->post("/ads/{$this->ad->id}/report", ['reason' => 'sold'])->assertRedirect();

        $this->assertSame(1, Report::count());
        $this->assertSame('sold', Report::first()->reason);
        $this->actingAs($buyer)->get($this->url())->assertSee('You reported this ad');
    }

    public function test_report_honeypot_and_own_ad(): void
    {
        $buyer = User::factory()->completed()->create();
        $this->actingAs($buyer)->post("/ads/{$this->ad->id}/report", ['reason' => 'scam', 'website' => 'spam.com'])->assertSessionHasErrors('website');
        $this->actingAs($this->ad->user)->post("/ads/{$this->ad->id}/report", ['reason' => 'scam'])->assertForbidden();
    }

    public function test_reports_switch_off(): void
    {
        app(FeatureManager::class)->set('ads.reports', false);
        $buyer = User::factory()->completed()->create();

        $this->actingAs($buyer)->get($this->url())->assertDontSee('Report this ad')->assertDontSee('Sign in to report');
        $this->actingAs($buyer)->post("/ads/{$this->ad->id}/report", ['reason' => 'scam'])->assertNotFound();
    }

    public function test_similar_ads_and_switch(): void
    {
        Ad::factory()->live()->create(['category_id' => $this->ad->category_id, 'title' => 'Another Hilux']);

        $this->get($this->url())->assertSee('Similar ads')->assertSee('Another Hilux');
        app(FeatureManager::class)->set('ads.similar', false);
        $this->get($this->url())->assertDontSee('Similar ads');
    }

    public function test_share_and_safety_switches(): void
    {
        app(FeatureManager::class)->set('share.buttons', false);
        app(FeatureManager::class)->set('site.safety_box', false);

        // The tip at the phone number has its own switch; without the safety box it has no "Safety tips" link.
        $this->get($this->url())->assertDontSee('Copy link')->assertSee('data-safety-tip', false)->assertDontSee('class="font-medium underline">Safety tips</a>', false);
        app(FeatureManager::class)->set('contact.safety_tip', false);
        PageCache::bump();
        $this->get($this->url())->assertDontSee('Meet in a busy public place');
    }

    public function test_sitemap_and_robots(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')->assertSee(url($this->url()), false)->assertSee(url('/cars'), false);
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /'); // not production → never indexed

        $this->app['env'] = 'production';
        $this->get('/robots.txt')->assertSee('Sitemap: '.url('/sitemap.xml'))->assertSee('Disallow: /admin');
    }
}
