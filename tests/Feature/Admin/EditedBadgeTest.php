<?php

namespace Tests\Feature\Admin;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\AdPhoto;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Ads\AdSnapshot;
use App\Support\Features\FeatureManager;

class EditedBadgeTest extends AdminTestCase
{
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->completed()->create(['city_id' => City::where('slug', 'mogadishu')->value('id')]);
    }

    private function pendingAd(): Ad
    {
        return Ad::factory()->create([
            'user_id' => $this->seller->id,
            'title' => 'Toyota Vitz 2012 automatic',
            'description' => 'Very clean car, new tyres, AC works well. No accidents.',
            'price' => 6500,
            'condition' => 'used',
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
            'fields' => ['brand' => 'toyota', 'model' => 'Vitz', 'year' => 2012],
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function sellerEdits(Ad $ad, array $overrides = []): void
    {
        $this->actingAs($this->seller)->put("/account/ads/{$ad->id}", array_merge([
            'title' => $ad->title, 'description' => $ad->description, 'price' => $ad->price, 'condition' => 'used',
            'city_id' => $ad->city_id, 'fields' => $ad->fields, 'photos' => $ad->photos()->pluck('id')->all(),
        ], $overrides))->assertRedirect()->assertSessionHasNoErrors();
    }

    /** @return array<string, array{before: string, after: string}> */
    private function changes(Ad $ad): array
    {
        $this->asStaff();
        $changes = $this->getJson("/admin/api/ads/{$ad->id}")->assertOk()->json('changes');

        return collect($changes ?? [])->keyBy('key')->map(fn ($c) => ['before' => $c['before'], 'after' => $c['after']])->all();
    }

    public function test_new_ad_is_not_marked_edited(): void
    {
        $ad = $this->pendingAd();
        $this->asStaff();

        $this->getJson('/admin/api/ads?status=pending')->assertOk()->assertJsonPath('data.0.edited', false);
        $this->getJson("/admin/api/ads/{$ad->id}")->assertJsonPath('changes', null);
    }

    public function test_edit_after_approval_shows_badge_and_exact_changes(): void
    {
        $ad = $this->pendingAd();
        $keep = AdPhoto::factory()->create(['ad_id' => $ad->id, 'user_id' => $this->seller->id, 'sort' => 0]);
        $this->asStaff();
        $this->postJson("/admin/api/ads/{$ad->id}/approve")->assertOk()->assertJsonPath('edited', false);

        $new = AdPhoto::factory()->create(['ad_id' => null, 'user_id' => $this->seller->id]);
        $this->sellerEdits($ad->fresh(), [
            'title' => 'Toyota Vitz 2013 automatic',
            'price' => 6000,
            'fields' => ['brand' => 'toyota', 'model' => 'Vitz', 'year' => 2013],
            'photos' => [$new->id, $keep->id],
        ]);

        $ad->refresh();
        $this->assertSame(AdStatus::Pending, $ad->status);
        $this->asStaff();
        $this->getJson('/admin/api/ads?status=pending')->assertJsonPath('data.0.edited', true);

        $changes = $this->changes($ad);
        $this->assertSame(['before' => 'Toyota Vitz 2012 automatic', 'after' => 'Toyota Vitz 2013 automatic'], $changes['title']);
        $this->assertSame(['before' => '$6,500', 'after' => '$6,000'], $changes['price']);
        $this->assertSame(['before' => '2012', 'after' => '2013'], $changes['field.year']);
        $this->assertSame(['before' => '1 photo', 'after' => '2 photos — 1 added'], $changes['photos']);
        $this->assertArrayNotHasKey('description', $changes);
        $this->assertArrayNotHasKey('place', $changes);
    }

    public function test_saving_without_changes_lists_nothing(): void
    {
        $ad = $this->pendingAd();
        $this->asStaff();
        $this->postJson("/admin/api/ads/{$ad->id}/approve")->assertOk();

        $this->sellerEdits($ad->fresh());

        $this->assertSame([], $this->changes($ad));
    }

    public function test_photo_order_change_is_shown(): void
    {
        $ad = $this->pendingAd();
        $a = AdPhoto::factory()->create(['ad_id' => $ad->id, 'user_id' => $this->seller->id, 'sort' => 0]);
        $b = AdPhoto::factory()->create(['ad_id' => $ad->id, 'user_id' => $this->seller->id, 'sort' => 1]);
        $this->asStaff();
        $this->postJson("/admin/api/ads/{$ad->id}/approve")->assertOk();

        $this->sellerEdits($ad->fresh(), ['photos' => [$b->id, $a->id]]);

        $this->assertSame('2 photos — Order changed (new cover photo?)', $this->changes($ad)['photos']['after']);
    }

    public function test_staff_edit_of_live_ad_becomes_the_approved_version(): void
    {
        $ad = $this->pendingAd();
        $this->asStaff();
        $this->postJson("/admin/api/ads/{$ad->id}/approve")->assertOk();
        $this->putJson("/admin/api/ads/{$ad->id}", ['title' => 'Toyota Vitz 2012 (fixed by staff)', 'description' => $ad->description, 'price' => 6500, 'price_negotiable' => false])->assertOk();

        $this->sellerEdits($ad->fresh(), ['price' => 6100]);

        $changes = $this->changes($ad);
        $this->assertSame(['price'], array_keys($changes)); // the staff title fix is not "changed by the seller"
    }

    public function test_switch_off_hides_badge_and_changes(): void
    {
        $ad = $this->pendingAd();
        $this->asStaff();
        $this->postJson("/admin/api/ads/{$ad->id}/approve")->assertOk();
        $this->sellerEdits($ad->fresh(), ['price' => 1]);

        app(FeatureManager::class)->set('ads.edit_changes', false);
        $this->asStaff();
        $this->getJson('/admin/api/ads?status=pending')->assertJsonPath('data.0.edited', false);
        $this->getJson("/admin/api/ads/{$ad->id}")->assertJsonPath('changes', null);
    }

    public function test_backfill_marks_old_approved_ads_without_touching_dates(): void
    {
        $live = Ad::factory()->live()->create(['reviewed_at' => now()->subDay(), 'updated_at' => now()->subDays(3)]);
        $pending = Ad::factory()->create(['reviewed_at' => now()->subDay()]);
        Ad::query()->toBase()->update(['approved_snapshot' => null]);
        $updatedAt = $live->fresh()->updated_at;

        AdSnapshot::backfill();

        $this->assertSame($live->title, $live->fresh()->approved_snapshot['title']);
        $this->assertEquals($updatedAt, $live->fresh()->updated_at);
        $this->assertNull($pending->fresh()->approved_snapshot); // waiting ads: version unknown
    }
}
