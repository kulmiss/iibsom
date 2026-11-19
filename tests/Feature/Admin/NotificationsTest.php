<?php

namespace Tests\Feature\Admin;

use App\Enums\AdStatus;
use App\Mail\CallBackMail;
use App\Mail\SavedSearchAlertMail;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\InboxItem;
use App\Models\User;
use App\Search\SearchCriteria;
use App\Support\Features\FeatureManager;
use App\Support\Notify\Inbox;
use App\Support\Settings\SettingsManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class NotificationsTest extends AdminTestCase
{
    private User $seller;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->seller = User::factory()->completed()->create(['name' => 'Seller Sahra', 'phone' => '+252611111111']);
        $this->buyer = User::factory()->completed()->create(['name' => 'Buyer Abdi', 'phone' => '+252622222222']);
    }

    private function ad(array $attributes = []): Ad
    {
        return Ad::factory()->create($attributes + [
            'user_id' => $this->seller->id, 'title' => 'Toyota Vitz 2012 automatic', 'description' => 'Very clean car, new tyres, AC works.',
            'price' => 6500, 'condition' => 'used',
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
            'fields' => ['brand' => 'toyota', 'model' => 'Vitz', 'year' => 2012],
        ]);
    }

    private function on(string $key, bool $value = true): void
    {
        app(FeatureManager::class)->set($key, $value);
    }

    private function sellerEdits(Ad $ad, int $price): void
    {
        $this->actingAs($this->seller)->put("/account/ads/{$ad->id}", [
            'title' => $ad->title, 'description' => $ad->description, 'price' => $price, 'condition' => 'used',
            'city_id' => $ad->city_id, 'fields' => $ad->fields, 'photos' => [],
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    // ---- Ad approved / not approved ----

    public function test_seller_hears_about_approval_and_rejection_in_their_language(): void
    {
        $ad = $this->ad();
        $other = $this->ad(['title' => 'Bad photos ad']);
        $this->asStaff();
        $this->postJson("/admin/api/ads/{$ad->id}/approve")->assertOk();
        $this->postJson("/admin/api/ads/{$other->id}/reject", ['reason' => 'Photos are not clear or not real'])->assertOk();

        $this->assertSame(2, Inbox::unread($this->seller));
        $home = $this->actingAs($this->seller)->get('/')->assertOk();
        $home->assertSee('href="'.url('/account/notifications').'"', false)->assertSee('2 new notifications');

        $list = $this->actingAs($this->seller)->get('/account/notifications')->assertOk();
        $list->assertSeeInOrder(['Your ad “Bad photos ad” was not approved: Photos are not clear or not real', 'Your ad “Toyota Vitz 2012 automatic” is approved and live.'], false);
        $list->assertSee('href="'.$ad->fresh()->url().'"', false);
        $this->assertSame(0, Inbox::unread($this->seller), 'opening the list reads it');

        $this->actingAs($this->seller)->get('/so/account/notifications')->assertOk()->assertSee('waa la oggolaaday');
    }

    public function test_people_only_see_their_own_notifications(): void
    {
        $ad = $this->ad();
        $this->asStaff();
        $this->postJson("/admin/api/ads/{$ad->id}/approve")->assertOk();

        $this->actingAs($this->buyer)->get('/account/notifications')->assertOk()->assertDontSee('Toyota Vitz 2012');
        $this->assertSame(1, Inbox::unread($this->seller));
    }

    public function test_settings_turn_kinds_off_and_keep_hidden_choices(): void
    {
        $this->on('contact.call_back', false);
        $this->actingAs($this->seller)->get('/account/notifications/settings')->assertOk()
            ->assertSee('My ad is approved')->assertDontSee('A buyer asks me to call');

        // Untick "approved"; call-back choices are hidden by the switch and must stay as they were.
        $this->actingAs($this->seller)->post('/account/notifications/settings', [
            'shown' => ['bell.ad_approved', 'bell.ad_rejected', 'bell.saved_search', 'email.saved_search', 'bell.price_drop'],
            'on' => ['bell.ad_rejected', 'bell.saved_search', 'email.saved_search', 'bell.price_drop', 'bell.hack'],
        ])->assertRedirect(url('/account/notifications/settings'))->assertSessionHas('status');

        $s = Inbox::settings($this->seller->fresh());
        $this->assertFalse($s['bell.ad_approved']);
        $this->assertTrue($s['bell.call_back']);
        $this->assertTrue($s['email.call_back']);
        $this->assertArrayNotHasKey('bell.hack', $s);

        $ad = $this->ad();
        $this->asStaff();
        $this->postJson("/admin/api/ads/{$ad->id}/approve")->assertOk();
        $this->assertSame(0, InboxItem::count());
    }

    // ---- Price drop on saved ads ----

    public function test_saved_ad_getting_cheaper_after_approval_tells_who_saved_it_once_a_day(): void
    {
        $this->on('favourites');
        $ad = $this->ad();
        $this->asStaff();
        $this->postJson("/admin/api/ads/{$ad->id}/approve")->assertOk();
        $this->actingAs($this->buyer)->postJson("/favourites/{$ad->id}")->assertOk();
        $this->assertSame(6500, (int) $this->buyer->favourites()->first()->pivot->price_when_saved);

        $this->sellerEdits($ad, 5900);
        $this->assertSame(AdStatus::Pending, $ad->fresh()->status);
        $this->assertSame(0, InboxItem::where('kind', 'price_drop')->count(), 'not before staff approve the change');

        $this->asStaff();
        $this->postJson("/admin/api/ads/{$ad->id}/approve")->assertOk();
        $item = InboxItem::where('kind', 'price_drop')->sole();
        $this->assertSame($this->buyer->id, $item->user_id);

        $this->actingAs($this->buyer)->get('/account/notifications')->assertOk()
            ->assertSee('Price dropped: “Toyota Vitz 2012 automatic” is now $5,900 (was $6,500).', false);
        $this->actingAs($this->buyer)->get('/account/saved')->assertOk()->assertSee('Cheaper: $6,500 → $5,900');

        // Lower again the same day: no second message; higher: never.
        $this->sellerEdits($ad, 5500);
        $this->asStaff();
        $this->postJson("/admin/api/ads/{$ad->id}/approve")->assertOk();
        $this->assertSame(1, InboxItem::where('kind', 'price_drop')->count());
    }

    public function test_price_drop_without_approval_and_not_for_higher_prices(): void
    {
        $this->on('favourites');
        $this->on('ads.approval', false);
        $ad = $this->ad(['status' => AdStatus::Live, 'published_at' => now()]);
        $this->buyer->favourites()->attach($ad->id, ['price_when_saved' => 6500]);

        $this->sellerEdits($ad, 7000);
        $this->assertSame(0, InboxItem::count());
        $this->sellerEdits($ad, 6000);
        $this->assertSame(1, InboxItem::where('kind', 'price_drop')->where('user_id', $this->buyer->id)->count());
        $this->assertSame(0, InboxItem::where('user_id', $this->seller->id)->where('kind', 'price_drop')->count());

        $this->on('favourites.price_drop', false);
        $this->actingAs($this->buyer)->get('/account/saved')->assertOk()->assertDontSee('Cheaper:');
    }

    // ---- Saved searches ----

    public function test_saved_search_alerts_go_to_the_bell_and_email_as_chosen(): void
    {
        $this->on('saved_searches');
        Mail::fake();
        $criteria = ['q' => 'toyota', 'min' => null, 'max' => null, 'condition' => null, 'category_id' => null, 'city_id' => null, 'fields' => []];
        $search = $this->buyer->savedSearches()->create(['label' => 'toyota', 'criteria' => $criteria, 'hash' => SearchCriteria::hash($criteria), 'checked_until' => now()->subHour()]);
        $this->ad(['status' => AdStatus::Live, 'published_at' => now()]);

        $this->artisan('iibsom:saved-search-alerts')->assertSuccessful()->run();
        Mail::assertSent(SavedSearchAlertMail::class, 1);
        $this->assertSame('1 new ad for your saved search “toyota”.', Inbox::text(InboxItem::sole()));

        // Email OFF: only the bell.
        $this->buyer->forceFill(['notify_settings' => ['email.saved_search' => false]])->save();
        $search->refresh()->forceFill(['checked_until' => now()->subHour()])->save();
        $this->artisan('iibsom:saved-search-alerts')->expectsOutputToContain('Sent 0 alert(s)')->assertSuccessful()->run();
        Mail::assertSent(SavedSearchAlertMail::class, 1);
        $this->assertSame(2, InboxItem::count());
        $this->assertTrue($search->fresh()->checked_until->isAfter(now()->subMinute()));
    }

    // ---- Request a call from the seller ----

    public function test_call_back_sends_the_buyers_number_to_the_seller_once_a_day(): void
    {
        Mail::fake();
        $ad = $this->ad(['status' => AdStatus::Live, 'published_at' => now()]);

        $page = $this->actingAs($this->buyer)->get($ad->url())->assertOk();
        $page->assertSee('data-call-back', false)->assertSee('+252 62 222 2222', false);
        auth()->logout();
        $this->get($ad->url())->assertOk()->assertSee('Request a call from the seller')->assertDontSee('data-call-back', false);

        $this->actingAs($this->buyer)->post("/ads/{$ad->id}/call-back")->assertRedirect($ad->url())
            ->assertSessionHas('status', 'Done. The seller has your number (+252 62 222 2222) and can call you.');
        $item = InboxItem::where('user_id', $this->seller->id)->sole();
        $this->assertSame('Buyer Abdi asks you to call +252 62 222 2222 about “Toyota Vitz 2012 automatic”.', Inbox::text($item));
        $this->assertSame('tel:+252622222222', Inbox::url($item));
        Mail::assertSent(CallBackMail::class, fn (CallBackMail $m) => $m->hasTo($this->seller->email) && $m->phone === '+252 62 222 2222');

        $this->actingAs($this->buyer)->post("/ads/{$ad->id}/call-back")->assertSessionHas('status', 'You already asked this seller today. Please wait for the call.');
        $this->assertSame(1, InboxItem::count());
        Mail::assertSent(CallBackMail::class, 1);
    }

    public function test_call_back_limits_and_refusals(): void
    {
        Mail::fake();
        $mine = $this->ad(['status' => AdStatus::Live, 'published_at' => now()]);
        $this->actingAs($this->seller)->post("/ads/{$mine->id}/call-back")->assertForbidden();
        $this->actingAs($this->seller)->get($mine->url())->assertDontSee('data-call-back', false);

        $waiting = $this->ad();
        $this->actingAs($this->buyer)->post("/ads/{$waiting->id}/call-back")->assertNotFound();

        // 10 different sellers a day at most.
        for ($i = 0; $i < 10; $i++) {
            InboxItem::create(['user_id' => $this->seller->id, 'kind' => 'call_back', 'data' => ['buyer' => $this->buyer->id], 'created_at' => now()]);
        }
        $this->actingAs($this->buyer)->post("/ads/{$mine->id}/call-back")->assertSessionHas('status', 'You asked 10 sellers today. Please try again tomorrow.');

        // Seller turned the email off: the bell still has it, no email.
        InboxItem::query()->delete();
        $this->seller->forceFill(['notify_settings' => ['email.call_back' => false, 'bell.call_back' => false]])->save();
        $this->actingAs($this->buyer)->post("/ads/{$mine->id}/call-back")->assertRedirect();
        Mail::assertNothingSent();
        $this->assertNotNull(InboxItem::sole()->read_at, 'kept, but not counted as new');

        $this->on('contact.call_back', false);
        $this->actingAs($this->buyer)->post("/ads/{$mine->id}/call-back")->assertNotFound();
        $this->actingAs($this->buyer)->get($mine->url())->assertDontSee('Request a call from the seller');
    }

    // ---- Switch and housekeeping ----

    public function test_switch_off_brings_back_the_old_bell_and_creates_nothing(): void
    {
        $this->on('notifications', false);
        $this->on('saved_searches');
        $ad = $this->ad();
        $this->asStaff();
        $this->postJson("/admin/api/ads/{$ad->id}/approve")->assertOk();
        $this->assertSame(0, InboxItem::count());

        $this->actingAs($this->seller)->get('/account/notifications')->assertNotFound();
        $this->actingAs($this->seller)->get('/')->assertOk()->assertSee('href="'.url('/account/searches').'" class="icon-btn"', false)
            ->assertDontSee('href="'.url('/account/notifications').'"', false);
    }

    public function test_old_notifications_are_deleted_every_night(): void
    {
        InboxItem::create(['user_id' => $this->seller->id, 'kind' => 'ad_approved', 'data' => ['title' => 'old'], 'created_at' => now()->subDays(181)]);
        InboxItem::create(['user_id' => $this->seller->id, 'kind' => 'ad_approved', 'data' => ['title' => 'new'], 'created_at' => now()->subDays(5)]);

        $event = collect(app(Schedule::class)->events())->first(fn ($e) => $e->description === 'prune-notifications');
        $this->assertNotNull($event);
        $event->run($this->app);

        $this->assertSame(['new'], InboxItem::pluck('data')->pluck('title')->all());
    }

    public function test_the_list_does_not_query_per_line(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $ad = $this->ad(['status' => AdStatus::Live, 'published_at' => now(), 'title' => "Ad number $i"]);
            InboxItem::create(['user_id' => $this->seller->id, 'kind' => 'ad_approved', 'ad_id' => $ad->id, 'data' => ['title' => $ad->title], 'created_at' => now()]);
        }
        $this->actingAs($this->seller)->get('/account/notifications')->assertOk(); // warm caches

        DB::enableQueryLog();
        $this->actingAs($this->seller)->get('/account/notifications')->assertOk()->assertSee('Ad number 14');
        $this->assertLessThanOrEqual(25, count(DB::getQueryLog()));
    }
}
