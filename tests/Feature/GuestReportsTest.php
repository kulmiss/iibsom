<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\Report;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Safety\GuestReports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GuestReportsTest extends TestCase
{
    use RefreshDatabase;

    private Ad $ad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->ad = Ad::factory()->live()->create([
            'user_id' => User::factory()->completed()->create()->id,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function report(array $data = [], ?Ad $ad = null): TestResponse
    {
        $ad ??= $this->ad;

        return $this->post("/ads/{$ad->id}/report-guest", $data + [
            'reason' => 'scam',
            'details' => 'Asked me to send money first',
            't' => GuestReports::token(now()->subSeconds(30)->getTimestamp()),
        ]);
    }

    private function state(TestResponse $res): string
    {
        $res->assertRedirect();
        parse_str((string) parse_url((string) $res->headers->get('Location'), PHP_URL_QUERY), $q);

        return (string) ($q['report'] ?? '');
    }

    public function test_guest_sees_the_form_and_can_report_without_cookies_or_ip(): void
    {
        $page = $this->get($this->ad->url())->assertOk()
            ->assertSee('report-guest', false)->assertSee('name="contact"', false)->assertDontSee('Sign in to report');
        $this->assertSame([], $page->headers->getCookies(), 'The ad page stays cookie-free.');

        $res = $this->report(['contact' => '+252 61 555 1234']);
        $this->assertSame('thanks', $this->state($res));
        $this->assertSame([], $res->headers->getCookies(), 'No cookie for a guest report.');

        $r = Report::firstOrFail();
        $this->assertNull($r->user_id);
        $this->assertSame(['scam', 'Asked me to send money first', '+252 61 555 1234', 1, 'waiting'], [$r->reason, $r->details, $r->contact, $r->times, $r->status]);
        $this->assertFalse(Schema::hasColumn('reports', 'ip_address'));
        $this->assertSame(0, DB::table('sessions')->count());

        $this->get($this->ad->url().'?report=thanks')->assertOk()->assertSee(__('ads.page.report_thanks'));
    }

    public function test_switches(): void
    {
        app(FeatureManager::class)->set('ads.guest_reports', false);
        $this->get($this->ad->url())->assertOk()->assertDontSee('report-guest', false)->assertSee('Sign in to report'); // the old way back
        $this->report()->assertNotFound();

        app(FeatureManager::class)->set('ads.guest_reports', true);
        app(FeatureManager::class)->set('ads.reports', false);
        $this->report()->assertNotFound();
        $this->assertSame(0, Report::count());
    }

    public function test_bots_are_dropped_quietly(): void
    {
        $this->assertSame('thanks', $this->state($this->report(['website' => 'http://spam.example'])));        // trap filled
        $this->assertSame('thanks', $this->state($this->report(['t' => GuestReports::token()])));                    // too fast
        $this->assertSame('thanks', $this->state($this->report(['t' => GuestReports::token(now()->subDay()->getTimestamp())]))); // too old
        $this->assertSame('thanks', $this->state($this->report(['t' => now()->subMinute()->getTimestamp().'.forged'])));   // not our stamp
        $this->assertSame('thanks', $this->state($this->report(['t' => ''])));
        $this->assertSame(0, Report::count());
    }

    public function test_bad_input_returns_to_the_form(): void
    {
        $this->assertSame('error', $this->state($this->report(['reason' => 'nonsense'])));
        $this->assertSame('error', $this->state($this->report(['contact' => '<script>'])));
        $this->assertSame(0, Report::count());
        $this->get($this->ad->url().'?report=error')->assertOk()->assertSee(__('ads.page.report_guest_error'));
    }

    public function test_same_report_again_is_merged_and_contact_kept(): void
    {
        $this->report();
        $this->report(['details' => '  ASKED me to send   money first ', 'contact' => 'ali@example.com']);
        $this->report(['reason' => 'sold']); // different reason = its own row

        $this->assertSame(2, Report::count());
        $scam = Report::where('reason', 'scam')->firstOrFail();
        $this->assertSame(2, $scam->times);
        $this->assertSame('ali@example.com', $scam->contact);
    }

    public function test_at_most_twenty_guest_reports_per_ad_per_day(): void
    {
        for ($i = 0; $i < GuestReports::PER_AD_DAY + 3; $i++) {
            GuestReports::store($this->ad, 'other', "note {$i}", null);
        }
        $this->assertSame(GuestReports::PER_AD_DAY, (int) Report::where('ad_id', $this->ad->id)->sum('times'));
        $this->assertSame('thanks', $this->state($this->report())); // still "thanks", nothing saved
        $this->assertSame(GuestReports::PER_AD_DAY, (int) Report::where('ad_id', $this->ad->id)->sum('times'));
    }

    public function test_sum_is_asked_only_when_many_reports_arrive(): void
    {
        Cache::put('guest-reports:'.now()->format('YmdH'), GuestReports::UNDER_ATTACK, 3600);
        $token = GuestReports::token(now()->subSeconds(30)->getTimestamp());
        [$a, $b] = GuestReports::sum($token);

        $this->assertSame('check', $this->state($this->report(['t' => $token])));
        $this->assertSame('check', $this->state($this->report(['t' => $token, 'sum' => $a + $b + 1])));
        $this->assertSame(0, Report::count());
        $this->get($this->ad->url())->assertOk()->assertSee('name="sum"', false);

        $this->assertSame('thanks', $this->state($this->report(['t' => $token, 'sum' => (string) ($a + $b)])));
        $this->assertSame(1, Report::count());
    }

    public function test_rate_limit_per_visitor(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->report(['reason' => Report::REASONS[$i]])->assertRedirect();
        }
        $this->report()->assertStatus(429);
    }

    public function test_signed_in_users_keep_their_own_flow(): void
    {
        $user = User::factory()->completed()->create();
        $this->actingAs($user)->get($this->ad->url())->assertOk()->assertDontSee('report-guest', false)->assertSee('name="_token"', false);
        $this->actingAs($user)->post("/ads/{$this->ad->id}/report", ['reason' => 'scam'])->assertRedirect();
        $this->assertSame($user->id, Report::firstOrFail()->user_id);
    }

    public function test_contacts_are_removed_30_days_after_handling(): void
    {
        $this->report(['contact' => '0615551234']);
        $this->report(['reason' => 'sold', 'contact' => '0615559999']);
        Report::where('reason', 'scam')->update(['status' => 'handled', 'handled_at' => now()->subDays(31)]);
        Report::where('reason', 'sold')->update(['status' => 'handled', 'handled_at' => now()->subDays(5)]);
        $this->assertSame(1, GuestReports::pruneContacts());
        $this->assertNull(Report::where('reason', 'scam')->firstOrFail()->contact);
        $this->assertSame('0615559999', Report::where('reason', 'sold')->firstOrFail()->contact, 'Kept while recent.');
    }
}
