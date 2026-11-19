<?php

namespace Tests\Feature\Admin;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\InboxItem;
use App\Models\SavedSearch;
use App\Models\User;
use App\Search\SearchCriteria;
use App\Support\Cache\PageCache;
use App\Support\Features\FeatureManager;
use App\Support\Notify\Inbox;
use App\Support\Settings\SettingsManager;
use Illuminate\Support\Facades\Mail;

class SellersFollowTest extends AdminTestCase
{
    private User $seller;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->seller = User::factory()->completed()->create(['name' => 'Seller Sahra']);
        $this->buyer = User::factory()->completed()->create(['name' => 'Buyer Abdi']);
    }

    private function ad(array $attributes = []): Ad
    {
        return Ad::factory()->create($attributes + [
            'user_id' => $this->seller->id, 'title' => 'Toyota Vitz 2012 automatic', 'price' => 6500,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);
    }

    // ---- Seller page ----

    public function test_seller_page_lists_only_live_ads_and_is_kept_out_of_google(): void
    {
        $live = $this->ad(['status' => AdStatus::Live, 'published_at' => now(), 'title' => 'Live Vitz']);
        $this->ad(['title' => 'Waiting Hilux']);
        $this->ad(['status' => AdStatus::Sold, 'published_at' => now(), 'title' => 'Sold Noah']);

        $page = $this->get('/seller/'.$this->seller->id)->assertOk();
        $page->assertSee('Seller Sahra')->assertSee('Live Vitz')->assertDontSee('Waiting Hilux')->assertDontSee('Sold Noah')
            ->assertSee('<meta name="robots" content="noindex, follow">', false)->assertSee('1 ad');
        $this->assertEmpty($page->headers->getCookies(), 'guest page stays cookie-free');
        $this->assertStringNotContainsString((string) $this->seller->phone, (string) $page->getContent());
        $this->assertStringNotContainsString((string) $this->seller->email, (string) $page->getContent());

        $this->get($live->url())->assertOk()->assertSee('href="'.url('/seller/'.$this->seller->id).'"', false);
        $this->get('/so/seller/'.$this->seller->id)->assertOk()->assertSee('Dhammaan xayeysiisyada iibiyahan');
    }

    public function test_no_page_for_banned_staff_unknown_or_unfinished_people(): void
    {
        $this->get('/seller/999999')->assertNotFound();
        $this->get('/seller/'.$this->staff('moderator')->id)->assertNotFound();
        $this->get('/seller/'.User::factory()->create()->id)->assertNotFound(); // profile not finished
        $this->seller->forceFill(['banned_at' => now()])->save();
        $this->get('/seller/'.$this->seller->id)->assertNotFound();
    }

    public function test_seller_page_switch(): void
    {
        $ad = $this->ad(['status' => AdStatus::Live, 'published_at' => now()]);
        app(FeatureManager::class)->set('sellers.page', false);
        PageCache::bump();
        $this->get('/seller/'.$this->seller->id)->assertNotFound();
        $this->get($ad->url())->assertOk()->assertDontSee('data-seller-link', false);
    }

    // ---- Follow ----

    public function test_follow_unfollow_and_new_ads_come_to_the_bell(): void
    {
        $this->actingAs($this->buyer)->get('/seller/'.$this->seller->id)->assertOk()->assertSee('aria-pressed="false"', false)->assertSee('>Follow<', false);
        $this->actingAs($this->buyer)->post('/follow/'.$this->seller->id)->assertRedirect()
            ->assertSessionHas('status', 'You follow Seller Sahra. Their new ads will come to your notifications.');
        $this->actingAs($this->buyer)->get('/seller/'.$this->seller->id)->assertOk()->assertSee('>Following<', false);
        $this->actingAs($this->buyer)->get('/account/following')->assertOk()->assertSee('Seller Sahra');

        // New ad approved: one line in the follower's bell. Edits approved later: nothing more.
        $ad = $this->ad();
        $this->asStaff();
        $this->postJson("/admin/api/ads/{$ad->id}/approve")->assertOk();
        $item = InboxItem::where('kind', 'followed_ad')->sole();
        $this->assertSame($this->buyer->id, $item->user_id);
        $this->assertSame('Seller Sahra, whom you follow, has a new ad: “Toyota Vitz 2012 automatic”.', Inbox::text($item));

        $ad->refresh()->forceFill(['status' => AdStatus::Pending])->save();
        $this->postJson("/admin/api/ads/{$ad->id}/approve")->assertOk();
        $this->assertSame(1, InboxItem::where('kind', 'followed_ad')->count());

        // The seller sees how many, never who.
        $this->actingAs($this->seller)->get('/seller/'.$this->seller->id)->assertOk()->assertSee('1 follower')->assertDontSee('Buyer Abdi')->assertDontSee('data-follow', false);

        $this->actingAs($this->buyer)->post('/follow/'.$this->seller->id)->assertSessionHas('status', 'You no longer follow Seller Sahra.');
        $this->assertSame(0, $this->buyer->following()->count());
    }

    public function test_follow_refusals_and_limits(): void
    {
        $this->actingAs($this->seller)->post('/follow/'.$this->seller->id)->assertStatus(422);
        $this->actingAs($this->buyer)->post('/follow/'.$this->staff('support')->id)->assertNotFound();
        $this->actingAs($this->buyer)->post('/follow/999999')->assertNotFound();

        // Followers who turned the kind off get nothing; demo ads never notify.
        $this->buyer->following()->attach($this->seller->id, ['created_at' => now()]);
        $this->buyer->forceFill(['notify_settings' => ['bell.followed_ad' => false]])->save();
        $ad = $this->ad();
        $this->asStaff();
        $this->postJson("/admin/api/ads/{$ad->id}/approve")->assertOk();
        $this->assertSame(0, InboxItem::where('kind', 'followed_ad')->count());

        app(FeatureManager::class)->set('sellers.follow', false);
        $this->actingAs($this->buyer)->post('/follow/'.$this->seller->id)->assertNotFound();
        $this->actingAs($this->buyer)->get('/seller/'.$this->seller->id)->assertOk()->assertDontSee('data-follow', false);
    }

    public function test_guests_see_a_follow_button_that_signs_in(): void
    {
        $this->get('/seller/'.$this->seller->id)->assertOk()->assertSee('title="Sign in to follow this seller"', false);
    }

    // ---- Rename and pause saved searches ----

    public function test_rename_and_pause_a_saved_search(): void
    {
        app(FeatureManager::class)->set('saved_searches', true);
        Mail::fake();
        $criteria = ['q' => 'toyota', 'min' => null, 'max' => null, 'condition' => null, 'category_id' => null, 'city_id' => null, 'fields' => []];
        $search = $this->buyer->savedSearches()->create(['label' => 'toyota', 'criteria' => $criteria, 'hash' => SearchCriteria::hash($criteria), 'checked_until' => now()->subHour()]);
        $this->ad(['status' => AdStatus::Live, 'published_at' => now()]);

        $this->actingAs($this->buyer)->patch("/account/searches/{$search->id}", ['label' => '  <b>Cheap</b> Toyotas '])->assertRedirect(url('/account/searches'));
        $this->assertSame('Cheap Toyotas', $search->fresh()->label);

        $this->actingAs($this->buyer)->patch("/account/searches/{$search->id}", ['muted' => '1'])->assertSessionHas('status', 'Alerts paused. The search stays here; turn alerts on again any time.');
        $this->actingAs($this->buyer)->get('/account/searches')->assertOk()->assertSee('Alerts paused')->assertSee('Turn alerts on');
        $this->artisan('iibsom:saved-search-alerts')->assertSuccessful()->run();
        Mail::assertNothingSent();
        $this->assertSame(0, InboxItem::count());

        $this->actingAs($this->buyer)->patch("/account/searches/{$search->id}", ['muted' => '0'])->assertSessionHas('status', 'Alerts are on again.');
        $this->artisan('iibsom:saved-search-alerts')->assertSuccessful()->run();
        Mail::assertSentCount(1);
    }

    public function test_others_cannot_change_my_saved_search_and_bad_input_is_refused(): void
    {
        app(FeatureManager::class)->set('saved_searches', true);
        $criteria = ['q' => 'x', 'min' => null, 'max' => null, 'condition' => null, 'category_id' => null, 'city_id' => null, 'fields' => []];
        $search = $this->buyer->savedSearches()->create(['label' => 'mine', 'criteria' => $criteria, 'hash' => SearchCriteria::hash($criteria), 'checked_until' => now()]);

        $this->actingAs($this->seller)->patch("/account/searches/{$search->id}", ['label' => 'hacked'])->assertNotFound();
        $this->actingAs($this->buyer)->patch("/account/searches/{$search->id}", ['label' => str_repeat('a', 81)])->assertSessionHasErrors('label');
        $this->actingAs($this->buyer)->patch("/account/searches/{$search->id}", ['muted' => 'maybe'])->assertSessionHasErrors('muted');
        $this->assertSame('mine', SavedSearch::find($search->id)->label);

        app(FeatureManager::class)->set('saved_searches.manage', false);
        $this->actingAs($this->buyer)->patch("/account/searches/{$search->id}", ['label' => 'new'])->assertNotFound();
        $this->actingAs($this->buyer)->get('/account/searches')->assertOk()->assertDontSee('Pause alerts');
    }
}
