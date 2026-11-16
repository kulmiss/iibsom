<?php

namespace Tests\Feature\Admin;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\AdminActivity;
use App\Models\BlockedPhone;
use App\Models\Category;
use App\Models\City;
use App\Models\Report;
use App\Models\User;

class AdminModerationTest extends AdminTestCase
{
    public function test_list_ads_with_status_filter_and_search(): void
    {
        $this->asStaff('support');
        Ad::factory()->create(['title' => 'Toyota Hilux 2015']);
        Ad::factory()->live()->create(['title' => 'Samsung A54 phone']);

        $this->getJson('/admin/api/ads?status=pending')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Toyota Hilux 2015');
        $this->getJson('/admin/api/ads?search=samsung')->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'live');
        $this->getJson('/admin/api/ads?sort=hack')->assertUnprocessable();
    }

    public function test_ad_detail_shows_fields_with_labels(): void
    {
        $this->asStaff('support');
        $ad = Ad::factory()->create(['category_id' => Category::where('slug', 'cars')->value('id'), 'fields' => ['brand' => 'toyota', 'mileage' => 90000]]);

        $this->getJson("/admin/api/ads/{$ad->id}")->assertOk()
            ->assertJsonFragment(['label' => 'Brand', 'value' => 'Toyota'])
            ->assertJsonFragment(['label' => 'Kilometres', 'value' => '90000 km'])
            ->assertJsonPath('seller_info.id', $ad->user_id);
    }

    public function test_approve_makes_ad_live_with_expiry_counts_and_log(): void
    {
        $mod = $this->asStaff('moderator');
        $cars = Category::where('slug', 'cars')->first();
        $ad = Ad::factory()->create(['category_id' => $cars->id]);

        $this->postJson("/admin/api/ads/{$ad->id}/approve")->assertOk()->assertJsonPath('status', 'live');

        $ad->refresh();
        $this->assertSame(AdStatus::Live, $ad->status);
        $this->assertSame($mod->id, $ad->reviewed_by);
        $this->assertEqualsWithDelta(now()->addDays(60)->timestamp, $ad->expires_at->timestamp, 5);
        $this->assertSame(1, $cars->fresh()->ads_count);
        $this->assertSame(1, $cars->parent->fresh()->ads_count);
        $this->assertSame(1, City::where('slug', 'mogadishu')->value('ads_count'));
        $this->assertTrue(AdminActivity::where('action', 'ad.approved')->where('subject_id', $ad->id)->exists());
    }

    public function test_cannot_approve_a_sold_ad(): void
    {
        $this->asStaff();
        $ad = Ad::factory()->create(['status' => AdStatus::Sold]);

        $this->postJson("/admin/api/ads/{$ad->id}/approve")->assertUnprocessable();
    }

    public function test_reject_needs_a_reason(): void
    {
        $this->asStaff('moderator');
        $ad = Ad::factory()->create();

        $this->postJson("/admin/api/ads/{$ad->id}/reject", ['reason' => ''])->assertUnprocessable();
        $this->postJson("/admin/api/ads/{$ad->id}/reject", ['reason' => 'Photos are not real'])->assertOk()->assertJsonPath('rejection_reason', 'Photos are not real');
        $this->assertSame(AdStatus::Rejected, $ad->fresh()->status);
    }

    public function test_bulk_approve_and_reject(): void
    {
        $this->asStaff('moderator');
        $ads = Ad::factory()->count(3)->create();
        $sold = Ad::factory()->create(['status' => AdStatus::Sold]);

        $this->postJson('/admin/api/ads/bulk', ['ids' => [...$ads->pluck('id'), $sold->id], 'action' => 'approve'])
            ->assertOk()->assertJson(['done' => 3, 'skipped' => 1]);
        $this->assertSame(3, Ad::where('status', AdStatus::Live)->count());

        $this->postJson('/admin/api/ads/bulk', ['ids' => $ads->pluck('id'), 'action' => 'reject'])->assertUnprocessable(); // reason needed
    }

    public function test_support_cannot_moderate_or_delete(): void
    {
        $this->asStaff('support');
        $ad = Ad::factory()->create();

        $this->postJson("/admin/api/ads/{$ad->id}/approve")->assertForbidden();
        $this->deleteJson("/admin/api/ads/{$ad->id}")->assertForbidden();
        $this->postJson('/admin/api/ads/bulk', ['ids' => [$ad->id], 'action' => 'approve'])->assertForbidden();
        $this->assertSame(AdStatus::Pending, $ad->fresh()->status);
    }

    public function test_edit_and_soft_delete(): void
    {
        $this->asStaff();
        $ad = Ad::factory()->live()->create();

        $this->putJson("/admin/api/ads/{$ad->id}", ['title' => '<b>Clean title here</b>', 'description' => 'A long enough description for the ad.', 'price' => 50])
            ->assertOk()->assertJsonPath('title', 'Clean title here');

        $this->deleteJson("/admin/api/ads/{$ad->id}")->assertOk();
        $this->assertSoftDeleted($ad);
        $this->getJson("/admin/api/ads/{$ad->id}")->assertOk(); // still visible to staff
    }

    public function test_ban_user_hides_ads_and_blocks_phone(): void
    {
        $this->asStaff('moderator');
        $user = User::factory()->completed()->create();
        $live = Ad::factory()->live()->create(['user_id' => $user->id]);

        $this->postJson("/admin/api/users/{$user->id}/ban", ['reason' => 'Scammer', 'block_phone' => true, 'hide_ads' => true])
            ->assertOk()->assertJsonPath('banned', true);

        $this->assertTrue($user->fresh()->isBanned());
        $this->assertSame(AdStatus::Rejected, $live->fresh()->status);
        $this->assertTrue(BlockedPhone::isBlocked($user->phone));

        $this->postJson("/admin/api/users/{$user->id}/unban")->assertOk()->assertJsonPath('banned', false);
    }

    public function test_staff_cannot_be_banned_from_users_screen(): void
    {
        $this->asStaff('moderator');
        $other = $this->staff('support');

        $this->postJson("/admin/api/users/{$other->id}/ban", ['reason' => 'x x x'])->assertUnprocessable();
        $this->getJson("/admin/api/users/{$other->id}")->assertNotFound();
    }

    public function test_users_search_by_phone_and_filter_banned(): void
    {
        $this->asStaff('support');
        User::factory()->completed()->create(['phone' => '+252615550000', 'name' => 'Faadumo']);
        User::factory()->completed()->banned()->create(['name' => 'Bad Guy']);

        $this->getJson('/admin/api/users?search=061 555 0000')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Faadumo');
        $this->getJson('/admin/api/users?state=banned')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Bad Guy');
    }

    public function test_blocked_phone_cannot_be_used_by_customers(): void
    {
        $this->asStaff('moderator');
        $this->postJson('/admin/api/blocked-phones', ['phone' => '061 5 999 999', 'reason' => 'Scam'])->assertCreated();
        $this->postJson('/admin/api/blocked-phones', ['phone' => '123'])->assertUnprocessable();

        $customer = User::factory()->create();
        $this->actingAs($customer)->post('/welcome', [
            'name' => 'Xamar', 'city_id' => City::where('slug', 'mogadishu')->value('id'), 'phone' => '615999999', 'terms' => '1',
        ])->assertSessionHasErrors('phone');
    }

    public function test_reports_queue_and_remove_ad(): void
    {
        $this->asStaff('moderator');
        $ad = Ad::factory()->live()->create();
        $reporters = User::factory()->count(2)->completed()->create();
        foreach ($reporters as $r) {
            $report = new Report(['reason' => 'scam', 'details' => 'Asked for money first']);
            $report->ad_id = $ad->id;
            $report->user_id = $r->id;
            $report->save();
        }

        $this->getJson('/admin/api/reports?status=waiting')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.reason_label', 'Scam or fraud');

        $first = Report::first();
        $this->postJson("/admin/api/reports/{$first->id}/handle", ['action' => 'remove_ad', 'note' => 'Confirmed scam'])->assertOk();

        $this->assertSame(AdStatus::Rejected, $ad->fresh()->status);
        $this->assertSame(0, Report::where('status', 'waiting')->count()); // both handled
    }

    public function test_dismiss_report(): void
    {
        $this->asStaff('moderator');
        $ad = Ad::factory()->live()->create();
        $report = new Report(['reason' => 'sold']);
        $report->ad_id = $ad->id;
        $report->user_id = User::factory()->create()->id;
        $report->save();

        $this->postJson("/admin/api/reports/{$report->id}/handle", ['action' => 'dismiss'])->assertOk()->assertJsonPath('status', 'rejected');
        $this->assertSame(AdStatus::Live, $ad->fresh()->status);
    }

    public function test_demo_ads_are_hidden_from_lists_unless_switched_on(): void
    {
        $this->asStaff();
        $demo = Ad::factory()->create();
        $demo->forceFill(['is_demo' => true])->save();

        $this->getJson('/admin/api/ads')->assertJsonCount(0, 'data');
    }

    public function test_live_counter_ignores_demo_unless_shown_and_follows_expiry(): void
    {
        $cars = Category::where('slug', 'cars')->first();
        $ad = Ad::factory()->live()->create(['category_id' => $cars->id]);
        $demo = Ad::factory()->live()->create(['category_id' => $cars->id]);
        $demo->forceFill(['is_demo' => true])->save();
        $this->assertSame(1, $cars->fresh()->ads_count);

        $ad->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->assertSame(0, $cars->fresh()->ads_count);
    }
}
