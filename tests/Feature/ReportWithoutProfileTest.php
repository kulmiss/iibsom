<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PR 81 (owner, 2026-11-19): a signed-in person can report an ad without first finishing their profile
 * (name, city, phone) — guests can already report without any of it. Posting still needs a full profile.
 */
class ReportWithoutProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_signed_in_person_without_a_finished_profile_can_report(): void
    {
        $ad = Ad::factory()->live()->create();
        $user = User::factory()->create(['profile_completed_at' => null]);
        $this->assertFalse($user->hasCompletedProfile());

        $this->actingAs($user)->post(lroute('ad.report', ['ad' => $ad->id]), ['reason' => 'scam', 'details' => 'Asks for money first'])
            ->assertRedirect($ad->url().'#report')
            ->assertSessionHas('status', __('ads.page.report_thanks'));

        $this->assertDatabaseHas('reports', ['ad_id' => $ad->id, 'user_id' => $user->id, 'reason' => 'scam', 'status' => 'waiting']);
    }

    public function test_the_rest_of_the_report_rules_still_hold(): void
    {
        $user = User::factory()->create(['profile_completed_at' => null]);

        // Own ad: no.
        $own = Ad::factory()->live()->create(['user_id' => $user->id]);
        $this->actingAs($user)->post(lroute('ad.report', ['ad' => $own->id]), ['reason' => 'scam'])->assertForbidden();

        // A wrong reason: no.
        $ad = Ad::factory()->live()->create();
        $this->actingAs($user)->post(lroute('ad.report', ['ad' => $ad->id]), ['reason' => 'nonsense'])->assertSessionHasErrors('reason');

        // Reporting twice keeps one report (the newest reason).
        $this->actingAs($user)->post(lroute('ad.report', ['ad' => $ad->id]), ['reason' => 'scam']);
        $this->actingAs($user)->post(lroute('ad.report', ['ad' => $ad->id]), ['reason' => 'sold']);
        $this->assertSame(1, Report::where('ad_id', $ad->id)->where('user_id', $user->id)->count());
    }

    public function test_posting_an_ad_still_needs_a_finished_profile(): void
    {
        $user = User::factory()->create(['profile_completed_at' => null]);
        $this->actingAs($user)->get('/post')->assertRedirect(lroute('welcome'));
    }

    public function test_banned_people_still_cannot_report(): void
    {
        $ad = Ad::factory()->live()->create();
        $user = User::factory()->create(['profile_completed_at' => null, 'banned_at' => now()]);

        $this->actingAs($user)->post(lroute('ad.report', ['ad' => $ad->id]), ['reason' => 'scam']);
        $this->assertDatabaseMissing('reports', ['ad_id' => $ad->id, 'user_id' => $user->id]);
    }
}
