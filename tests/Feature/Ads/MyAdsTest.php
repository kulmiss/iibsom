<?php

namespace Tests\Feature\Ads;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\AdPhoto;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Features\FeatureManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyAdsTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seller = User::factory()->completed()->create(['city_id' => City::where('slug', 'mogadishu')->value('id')]);
    }

    private function ad(array $attrs = [], ?User $owner = null): Ad
    {
        return Ad::factory()->live()->create(array_merge([
            'user_id' => ($owner ?? $this->seller)->id,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ], $attrs));
    }

    private function editData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Toyota Vitz 2012 automatic, clean',
            'description' => 'Very clean car, new tyres, AC works well. No accidents.',
            'price' => 6000,
            'condition' => 'used',
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
            'fields' => ['brand' => 'toyota', 'model' => 'Vitz', 'year' => 2012, 'mileage' => 98000, 'fuel' => 'petrol', 'transmission' => 'automatic'],
        ], $overrides);
    }

    public function test_guests_cannot_open_my_ads(): void
    {
        $this->get('/account/ads')->assertRedirect(url('/login'));
    }

    public function test_tabs_show_only_my_ads_with_counts(): void
    {
        $mine = $this->ad(['title' => 'My own Toyota for sale']);
        $this->ad(['title' => 'Pending ad of mine', 'status' => AdStatus::Pending]);
        $this->ad(['title' => 'Someone else car'], User::factory()->completed()->create());

        $this->actingAs($this->seller)->get('/account/ads')
            ->assertOk()->assertSee('My own Toyota for sale')
            ->assertDontSee('Someone else car')->assertDontSee('Pending ad of mine')
            ->assertSee(url('/account/ads/'.$mine->id.'/edit'), false);

        $this->actingAs($this->seller)->get('/account/ads?tab=pending')->assertSee('Pending ad of mine');
    }

    public function test_rejected_tab_shows_the_reason(): void
    {
        $this->ad(['status' => AdStatus::Rejected, 'rejection_reason' => 'Photo is blurry']);

        $this->actingAs($this->seller)->get('/account/ads?tab=rejected')->assertSee('Why: Photo is blurry');
    }

    public function test_edit_form_is_prefilled(): void
    {
        $ad = $this->ad(['title' => 'Prefilled title here', 'price' => 4321]);

        $this->actingAs($this->seller)->get("/account/ads/{$ad->id}/edit")
            ->assertOk()->assertSee('value="Prefilled title here"', false)->assertSee('value="4321"', false)
            ->assertSee('Save changes');
    }

    public function test_saving_an_edit_sends_the_ad_back_for_approval(): void
    {
        $ad = $this->ad();

        $this->actingAs($this->seller)->put("/account/ads/{$ad->id}", $this->editData())
            ->assertRedirect(url('/account/ads?tab=pending'))->assertSessionHas('status');

        $ad->refresh();
        $this->assertSame(AdStatus::Pending, $ad->status);
        $this->assertSame(6000, $ad->price);
        $this->assertSame('Toyota Vitz 2012 automatic, clean', $ad->title);
    }

    public function test_edit_stays_live_when_approval_is_off(): void
    {
        app(FeatureManager::class)->set('ads.approval', false);
        $ad = $this->ad();

        $this->actingAs($this->seller)->put("/account/ads/{$ad->id}", $this->editData())->assertRedirect();
        $this->assertSame(AdStatus::Live, $ad->refresh()->status);
    }

    public function test_photos_can_be_reordered_added_and_removed_on_edit(): void
    {
        $ad = $this->ad();
        $keep = AdPhoto::factory()->create(['ad_id' => $ad->id, 'user_id' => $this->seller->id, 'sort' => 0]);
        $drop = AdPhoto::factory()->create(['ad_id' => $ad->id, 'user_id' => $this->seller->id, 'sort' => 1]);
        $new = AdPhoto::factory()->create(['ad_id' => null, 'user_id' => $this->seller->id]);
        $stranger = AdPhoto::factory()->create(['ad_id' => null, 'user_id' => User::factory()->create()->id]);

        $this->actingAs($this->seller)->put("/account/ads/{$ad->id}", $this->editData(['photos' => [$new->id, $keep->id, $stranger->id]]))->assertRedirect();

        $this->assertSame([$new->id, $keep->id], $ad->photos()->pluck('id')->all());
        $this->assertNull(AdPhoto::find($drop->id));
        $this->assertNull($stranger->refresh()->ad_id);
    }

    public function test_cannot_touch_someone_elses_ad(): void
    {
        $other = $this->ad([], User::factory()->completed()->create());
        $me = $this->actingAs($this->seller);

        $me->get("/account/ads/{$other->id}/edit")->assertNotFound();
        $me->put("/account/ads/{$other->id}", $this->editData())->assertNotFound();
        $me->post("/account/ads/{$other->id}/sold")->assertNotFound();
        $me->post("/account/ads/{$other->id}/renew")->assertNotFound();
        $me->delete("/account/ads/{$other->id}")->assertNotFound();
        $this->assertSame(AdStatus::Live, $other->refresh()->status);
        $this->assertNull($other->deleted_at);
    }

    public function test_mark_as_sold(): void
    {
        $ad = $this->ad();

        $this->actingAs($this->seller)->from('/account/ads')->post("/account/ads/{$ad->id}/sold")->assertRedirect('/account/ads');
        $ad->refresh();
        $this->assertSame(AdStatus::Sold, $ad->status);
        $this->assertNotNull($ad->sold_at);
        // Sold ads are not public and cannot be edited.
        $this->actingAs($this->seller)->get("/account/ads/{$ad->id}/edit")->assertNotFound();
    }

    public function test_only_live_ads_can_be_marked_sold(): void
    {
        $ad = $this->ad(['status' => AdStatus::Pending]);
        $this->actingAs($this->seller)->post("/account/ads/{$ad->id}/sold")->assertNotFound();
    }

    public function test_renew_an_expired_ad(): void
    {
        $ad = $this->ad(['status' => AdStatus::Expired, 'expires_at' => now()->subDay()]);

        $this->actingAs($this->seller)->post("/account/ads/{$ad->id}/renew")->assertRedirect(url('/account/ads'));
        $ad->refresh();
        $this->assertSame(AdStatus::Live, $ad->status);
        $this->assertTrue($ad->expires_at->between(now()->addDays(59), now()->addDays(61)));
    }

    public function test_renew_shows_only_near_the_end(): void
    {
        $fresh = $this->ad(['expires_at' => now()->addDays(40)]);
        $ending = $this->ad(['expires_at' => now()->addDays(3)]);

        $this->actingAs($this->seller)->post("/account/ads/{$fresh->id}/renew")->assertNotFound();
        $this->actingAs($this->seller)->post("/account/ads/{$ending->id}/renew")->assertRedirect();
        $this->assertTrue($ending->refresh()->expires_at->gt(now()->addDays(50)));
    }

    public function test_renew_is_gone_when_ads_do_not_expire(): void
    {
        app(FeatureManager::class)->set('ads.expiry', false);
        $ad = $this->ad(['status' => AdStatus::Expired, 'expires_at' => now()->subDay()]);

        $this->actingAs($this->seller)->post("/account/ads/{$ad->id}/renew")->assertNotFound();
    }

    public function test_delete_is_a_soft_delete(): void
    {
        $ad = $this->ad();

        $this->actingAs($this->seller)->delete("/account/ads/{$ad->id}")->assertRedirect();
        $this->assertSoftDeleted($ad);
        $this->get($ad->url())->assertNotFound();
    }

    public function test_switch_off_hides_my_ads(): void
    {
        app(FeatureManager::class)->set('ads.my_ads', false);
        $this->assertTrue(feature('ad_stats')); // its tiles linked to My ads too (PR 106)

        $this->actingAs($this->seller)->get('/account/ads')->assertNotFound();
        $this->actingAs($this->seller)->get('/account')->assertOk()->assertDontSee(url('/account/ads'));
    }
}
