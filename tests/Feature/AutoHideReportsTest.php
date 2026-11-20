<?php

namespace Tests\Feature;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\InboxItem;
use App\Models\Report;
use App\Models\User;
use App\Support\Ads\AdForm;
use App\Support\Ads\AutoHide;
use App\Support\Ads\Moderation;
use App\Support\Cache\PageCache;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Admin\AdminTestCase;

/** PR 89: an ad is hidden for a staff check after reports from N different signed-in people. */
class AutoHideReportsTest extends AdminTestCase
{
    private Ad $ad;

    protected function setUp(): void
    {
        parent::setUp();
        app(FeatureManager::class)->set('notifications', true);
        $this->ad = Ad::factory()->live()->create(['title' => 'Too good to be true phone', 'category_id' => Category::where('slug', 'cars')->value('id')]);
    }

    private function report(?User $by = null, string $reason = 'scam'): TestResponse
    {
        $by ??= User::factory()->completed()->create();

        return $this->actingAs($by)->post("/ads/{$this->ad->id}/report", ['reason' => $reason]);
    }

    public function test_hidden_after_three_different_people_and_the_seller_is_told(): void
    {
        $this->report()->assertRedirect($this->ad->url().'#report');
        $this->report()->assertRedirect($this->ad->url().'#report');
        $this->assertSame(AdStatus::Live, $this->ad->fresh()->status);

        $this->report()->assertRedirect(lroute('home'))->assertSessionHas('report_thanks');
        $ad = $this->ad->fresh();
        $this->assertSame(AdStatus::Pending, $ad->status);
        $this->assertSame(AutoHide::FLAG, $ad->flag);
        $this->assertSame('3', $ad->flag_word);

        // Gone from the site for others; the seller still sees it, with the reason in My ads.
        auth()->logout();
        PageCache::bump();
        $this->get($ad->url())->assertNotFound();
        $this->get('/cars')->assertDontSee('Too good to be true phone');
        $this->actingAs($ad->user)->get(lroute('account.ads', ['tab' => 'pending']))->assertOk()->assertSee('data-hidden-reports', false)
            ->assertSee('several people reported this ad');
        $this->assertSame(1, InboxItem::where('user_id', $ad->user_id)->where('kind', 'ad_hidden')->count());

        // Written in the activity log by the site itself (no staff member, no IP).
        $this->assertDatabaseHas('admin_activities', ['action' => 'ad.auto_hidden', 'user_id' => null, 'ip' => null, 'subject_id' => $ad->id]);
    }

    public function test_the_same_person_or_guests_do_not_add_up(): void
    {
        $same = User::factory()->completed()->create();
        $this->report($same);
        $this->report($same, 'offensive');
        $this->report($same, 'other');
        $this->assertSame(1, AutoHide::reporters($this->ad));

        // Guest reports (switch ON) never count.
        app(FeatureManager::class)->set('ads.guest_reports', true);
        foreach (range(1, 3) as $i) {
            Report::forceCreate(['ad_id' => $this->ad->id, 'user_id' => null, 'reason' => 'scam', 'status' => 'waiting', 'times' => 5]);
        }
        $this->report(); // 2 people
        $this->assertSame(AdStatus::Live, $this->ad->fresh()->status);

        // Banned reporters do not count either.
        $banned = User::factory()->completed()->create();
        $this->report($banned);
        $banned->forceFill(['banned_at' => now()])->save();
        $this->assertSame(2, AutoHide::reporters($this->ad));
    }

    public function test_the_number_is_a_setting_and_the_switch_turns_it_off(): void
    {
        app(SettingsManager::class)->set('reports.auto_hide_at', 5);
        foreach (range(1, 4) as $i) {
            $this->report();
        }
        $this->assertSame(AdStatus::Live, $this->ad->fresh()->status);
        $this->report();
        $this->assertSame(AdStatus::Pending, $this->ad->fresh()->status);

        // Switch OFF: reports only go to staff.
        $other = Ad::factory()->live()->create();
        $this->ad = $other;
        app(FeatureManager::class)->set('reports.auto_hide', false);
        foreach (range(1, 6) as $i) {
            $this->report();
        }
        $this->assertSame(AdStatus::Live, $other->fresh()->status);
    }

    public function test_only_a_live_ad_and_only_once(): void
    {
        foreach (range(1, 3) as $i) {
            $this->report();
        }
        $this->assertSame(1, DB::table('admin_activities')->where('action', 'ad.auto_hidden')->count());
        // More reports on the hidden ad: the ad page is closed to them; nothing changes.
        $this->report()->assertNotFound();
        $this->assertTrue(AutoHide::check($this->ad->fresh()) === false);
        $this->assertSame(1, DB::table('admin_activities')->where('action', 'ad.auto_hidden')->count());
    }

    public function test_staff_put_it_back_and_the_reports_close(): void
    {
        foreach (range(1, 3) as $i) {
            $this->report();
        }
        $first = Report::where('ad_id', $this->ad->id)->orderBy('id')->firstOrFail();
        $this->asStaff('moderator');
        $row = collect($this->getJson('/admin/api/reports')->json('data'))->firstWhere('id', $first->id);
        $this->assertTrue($row['ad_hidden']);

        $this->postJson("/admin/api/reports/{$first->id}/handle", ['action' => 'restore_ad', 'note' => 'Checked: real phone'])->assertOk();
        $ad = $this->ad->fresh();
        $this->assertSame(AdStatus::Live, $ad->status);
        $this->assertNull($ad->flag);
        $this->assertSame(0, Report::where('ad_id', $ad->id)->where('status', 'waiting')->count());
        $this->assertSame(3, Report::where('ad_id', $ad->id)->where('status', 'rejected')->where('staff_note', 'Checked: real phone')->count());

        // Reports from the same people no longer hide it (they were closed); 3 new people would.
        $this->assertSame(0, AutoHide::reporters($ad));

        // "Put back" only for an ad that was hidden after reports.
        $this->postJson("/admin/api/reports/{$first->id}/handle", ['action' => 'restore_ad'])->assertStatus(422);
    }

    public function test_approving_it_in_waiting_for_approval_also_closes_the_reports(): void
    {
        foreach (range(1, 3) as $i) {
            $this->report();
        }
        $this->asStaff('moderator');
        $pending = collect($this->getJson('/admin/api/ads?status=pending')->json('data'))->firstWhere('id', $this->ad->id);
        $this->assertStringContainsString('Hidden after reports from 3 people', (string) $pending['flag']);

        Moderation::approve($this->ad->fresh(), auth()->user());
        $this->assertSame(AdStatus::Live, $this->ad->fresh()->status);
        $this->assertSame(0, Report::where('ad_id', $this->ad->id)->where('status', 'waiting')->count());
    }

    public function test_editing_a_hidden_ad_does_not_put_it_back(): void
    {
        app(FeatureManager::class)->set('ads.approval', false); // edits normally go live at once
        foreach (range(1, 3) as $i) {
            $this->report();
        }
        $ad = $this->ad->fresh();
        app(AdForm::class)->update($ad, $ad->category, [
            'title' => 'Changed title only', 'description' => (string) $ad->description, 'price' => $ad->price,
            'city_id' => $ad->city_id, 'condition' => $ad->condition, 'fields' => $ad->fields ?? [], 'photos' => [],
            'price_negotiable' => false, 'content_hash' => 'x',
        ]);
        $ad->refresh();
        $this->assertSame('Changed title only', $ad->title);
        $this->assertSame(AdStatus::Pending, $ad->status);
        $this->assertSame(AutoHide::FLAG, $ad->flag);
    }

    public function test_settings_page_has_the_number(): void
    {
        $this->asStaff();
        $this->getJson('/admin/api/settings/general')->assertOk()->assertJsonPath('reports_auto_hide_at', 3);
        $general = $this->getJson('/admin/api/settings/general')->json();
        $general['reports_auto_hide_at'] = 1;
        $this->putJson('/admin/api/settings/general', $general)->assertUnprocessable()->assertJsonValidationErrors('reports_auto_hide_at');
        $general['reports_auto_hide_at'] = 4;
        $this->putJson('/admin/api/settings/general', $general)->assertOk();
        $this->assertSame(4, setting('reports.auto_hide_at'));
    }
}
