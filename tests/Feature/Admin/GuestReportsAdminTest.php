<?php

namespace Tests\Feature\Admin;

use App\Models\Ad;
use App\Models\Report;
use App\Models\User;
use App\Support\Safety\GuestReports;

class GuestReportsAdminTest extends AdminTestCase
{
    public function test_queue_shows_guest_vs_user_and_most_reported_ad_first(): void
    {
        $quiet = Ad::factory()->live()->create();
        $busy = Ad::factory()->live()->create();
        $user = User::factory()->completed()->create(['name' => 'Hodan Reporter']);

        $old = new Report(['reason' => 'sold']);
        $old->forceFill(['ad_id' => $quiet->id, 'user_id' => $user->id, 'status' => 'waiting'])->save(); // newest-first alone would put busy last
        GuestReports::store($busy, 'scam', 'Wants money first', '0615551234');
        GuestReports::store($busy, 'scam', 'wants money FIRST', null);
        GuestReports::store($busy, 'offensive', null, null);
        $latest = new Report(['reason' => 'other']);
        $latest->forceFill(['ad_id' => $quiet->id, 'user_id' => User::factory()->completed()->create()->id, 'status' => 'waiting'])->save();

        $this->asStaff('support');
        $rows = $this->getJson('/admin/api/reports')->assertOk()->json('data');

        $this->assertSame($busy->id, $rows[0]['ad_id'], 'The ad with the most waiting reports comes first.');
        $this->assertSame(3, $rows[0]['ad_reports']);
        $guest = collect($rows)->firstWhere('reason', 'scam');
        $this->assertSame(['Guest', true, '0615551234', 2], [$guest['reporter'], $guest['is_guest'], $guest['contact'], $guest['times']]);
        $named = collect($rows)->firstWhere('reason', 'sold');
        $this->assertSame(['Hodan Reporter', false], [$named['reporter'], $named['is_guest']]);
    }

    public function test_taking_the_ad_down_closes_guest_reports_too(): void
    {
        $ad = Ad::factory()->live()->create();
        GuestReports::store($ad, 'scam', null, null);
        GuestReports::store($ad, 'offensive', null, null);
        $this->asStaff('moderator');

        $this->postJson('/admin/api/reports/'.Report::firstOrFail()->id.'/handle', ['action' => 'remove_ad', 'note' => 'Scam'])->assertOk();
        $this->assertSame(0, Report::where('status', 'waiting')->count());
    }
}
