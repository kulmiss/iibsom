<?php

namespace Tests\Feature\Admin;

use App\Enums\AdStatus;
use App\Mail\SavedSearchAlertMail;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Search\SearchCriteria;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Support\Facades\Mail;

class CloseAndBumpTest extends AdminTestCase
{
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->seller = User::factory()->completed()->create();
    }

    private function live(array $attributes = []): Ad
    {
        return Ad::factory()->live()->create($attributes + [
            'user_id' => $this->seller->id, 'title' => 'Toyota Vitz close test', 'price' => 6500,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
            'published_at' => now()->subDays(10),
        ]);
    }

    // ---- Close with a reason ----

    public function test_close_with_each_reason(): void
    {
        $sold = $this->live();
        $this->actingAs($this->seller)->get('/account/ads')->assertOk()->assertSee('Close ad')->assertSee('Sold somewhere else')->assertDontSee('Mark as sold');

        $this->actingAs($this->seller)->post("/account/ads/{$sold->id}/close", ['reason' => 'sold_here'])->assertRedirect()->assertSessionHas('status', 'Marked as sold. Congratulations!');
        $sold->refresh();
        $this->assertSame(AdStatus::Sold, $sold->status);
        $this->assertNotNull($sold->sold_at);
        $this->assertSame('sold_here', $sold->closed_reason);

        $stop = $this->live();
        $this->actingAs($this->seller)->post("/account/ads/{$stop->id}/close", ['reason' => 'not_selling'])->assertSessionHas('status', 'Ad closed. You can renew it later from "Ended".');
        $stop->refresh();
        $this->assertSame(AdStatus::Expired, $stop->status);
        $this->assertFalse($stop->isLive());
        $this->get($stop->url())->assertDontSee('Show number');

        $this->actingAs($this->seller)->get('/account/ads?tab=ended')->assertOk()->assertSee('Closed: Not selling any more')->assertSee('Closed: Sold on iibsom');
        $this->actingAs($this->seller)->get('/so/account/ads?tab=ended')->assertOk()->assertSee('La xiray: Mar dambe ma iibinayo');

        // Renewing clears the reason.
        $this->actingAs($this->seller)->post("/account/ads/{$stop->id}/renew")->assertRedirect();
        $this->assertNull($stop->fresh()->closed_reason);

        // Staff see it.
        $this->asStaff();
        $this->getJson("/admin/api/ads/{$sold->id}")->assertOk()->assertJsonPath('closed_reason', 'Sold on iibsom');
    }

    public function test_close_refusals(): void
    {
        $ad = $this->live();
        $other = User::factory()->completed()->create();
        $this->actingAs($other)->post("/account/ads/{$ad->id}/close", ['reason' => 'sold_here'])->assertNotFound();
        $this->actingAs($this->seller)->post("/account/ads/{$ad->id}/close", ['reason' => 'hack'])->assertSessionHasErrors('reason');
        $this->actingAs($this->seller)->post("/account/ads/{$ad->id}/close", [])->assertSessionHasErrors('reason');
        $this->assertSame(AdStatus::Live, $ad->fresh()->status);

        app(FeatureManager::class)->set('ads.close_reason', false);
        $this->actingAs($this->seller)->post("/account/ads/{$ad->id}/close", ['reason' => 'sold_here'])->assertNotFound();
        $this->actingAs($this->seller)->get('/account/ads')->assertOk()->assertSee('Mark as sold')->assertDontSee('data-close-ad', false);
    }

    // ---- Free move to top ----

    public function test_move_to_top_once_every_seven_days(): void
    {
        $old = $this->live(['title' => 'Old Vitz']);
        $this->live(['title' => 'Newer Hilux', 'published_at' => now()->subDays(2)]);
        $this->get('/cars')->assertSeeInOrder(['Newer Hilux', 'Old Vitz']);

        $this->actingAs($this->seller)->get('/account/ads')->assertOk()->assertSee('Move to top');
        $this->actingAs($this->seller)->post("/account/ads/{$old->id}/bump")->assertSessionHas('status', 'Done. Your ad is at the top of the lists again.');
        $old->refresh();
        $this->assertTrue($old->published_at->isAfter(now()->subMinute()));
        $this->assertNotNull($old->bumped_at);
        auth()->logout();
        $this->get('/cars')->assertSeeInOrder(['Old Vitz', 'Newer Hilux']);

        // Again at once: refused with the date it is possible again.
        $before = $old->published_at;
        $this->actingAs($this->seller)->post("/account/ads/{$old->id}/bump")->assertSessionHas('status', fn ($s) => str_starts_with($s, 'You can move this ad up again on '));
        $this->assertTrue($old->fresh()->published_at->equalTo($before));
        $this->actingAs($this->seller)->get('/account/ads')->assertOk()->assertSee('Free move to top again on');
    }

    public function test_move_to_top_refusals(): void
    {
        $ad = $this->live();
        $other = User::factory()->completed()->create();
        $this->actingAs($other)->post("/account/ads/{$ad->id}/bump")->assertNotFound();
        $pending = $this->live(['status' => AdStatus::Pending]);
        $this->actingAs($this->seller)->post("/account/ads/{$pending->id}/bump")->assertNotFound();

        app(FeatureManager::class)->set('ads.free_bump', false);
        $this->actingAs($this->seller)->post("/account/ads/{$ad->id}/bump")->assertNotFound();
        $this->actingAs($this->seller)->get('/account/ads')->assertOk()->assertDontSee('Move to top');
    }

    public function test_a_moved_up_ad_is_not_sent_again_as_new_in_alerts(): void
    {
        app(FeatureManager::class)->set('saved_searches', true);
        Mail::fake();
        $buyer = User::factory()->completed()->create();
        $criteria = ['q' => 'vitz', 'min' => null, 'max' => null, 'condition' => null, 'category_id' => null, 'city_id' => null, 'fields' => []];
        $buyer->savedSearches()->create(['label' => 'vitz', 'criteria' => $criteria, 'hash' => SearchCriteria::hash($criteria), 'checked_until' => now()->subHour()]);
        $ad = $this->live();

        $this->actingAs($this->seller)->post("/account/ads/{$ad->id}/bump")->assertRedirect();
        $this->artisan('iibsom:saved-search-alerts')->assertSuccessful()->run();
        Mail::assertNotSent(SavedSearchAlertMail::class);

        // A really new ad still comes.
        $this->travel(2)->minutes();
        $this->live(['title' => 'Brand new Vitz', 'published_at' => now()]);
        $this->artisan('iibsom:saved-search-alerts')->assertSuccessful()->run();
        Mail::assertSent(SavedSearchAlertMail::class, fn (SavedSearchAlertMail $m) => $m->ads->pluck('title')->all() === ['Brand new Vitz']);
    }
}
