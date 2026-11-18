<?php

namespace Tests\Feature;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\AdminActivity;
use App\Models\Payment;
use App\Models\Report;
use App\Models\User;
use App\Support\Demo\DemoData;
use App\Support\Features\FeatureManager;
use App\Support\Money\Boosts;
use App\Support\Money\Ledger;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function seedDemo(): DemoSeeder
    {
        $seeder = $this->app->make(DemoSeeder::class);
        $seeder->withPhotos = false;
        $seeder->setContainer($this->app)->__invoke();

        return $seeder;
    }

    public function test_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('iib:demo-reset', ['--force' => true])->assertFailed();

        $this->expectException(RuntimeException::class);
        $this->seedDemo();
    }

    public function test_makes_a_full_realistic_set(): void
    {
        $seeder = $this->seedDemo();

        $this->assertGreaterThanOrEqual(300, Ad::where('is_demo', true)->count());
        $this->assertGreaterThanOrEqual(60, User::where('is_demo', true)->where('role', User::CUSTOMER)->count());
        $this->assertGreaterThanOrEqual(15, Report::where('is_demo', true)->count());
        $this->assertSame(0, Ad::where('is_demo', false)->count());

        // Every status, pending queue, history of 3+ months.
        foreach (AdStatus::cases() as $status) {
            $this->assertTrue(Ad::where('status', $status)->exists(), "no {$status->value} ads");
        }
        $this->assertGreaterThanOrEqual(20, Ad::where('status', AdStatus::Pending)->count());
        $this->assertGreaterThanOrEqual(5, Ad::where('status', AdStatus::Pending)->whereNotNull('approved_snapshot')->count()); // "Edited" examples
        $this->assertSame(0, Ad::whereIn('status', [AdStatus::Live, AdStatus::Sold, AdStatus::Expired])->whereNull('approved_snapshot')->count());
        $this->assertTrue(Ad::min('created_at') < now()->subDays(90));
        $this->assertGreaterThan(200, AdminActivity::where('is_demo', true)->count());

        // Staff with 2FA ready.
        $mod = User::where('email', 'demo-moderator@demo.iibsom.test')->first();
        $this->assertSame('moderator', $mod->role);
        $this->assertNotNull($mod->two_factor_confirmed_at);
        $this->assertArrayHasKey('support', $seeder->staffLogins);

        // Brands follow the title (iPhone = Apple, never a random brand).
        foreach (Ad::whereHas('category', fn ($q) => $q->where('slug', 'mobile-phones'))->get() as $phone) {
            if (str_contains($phone->title, 'iPhone')) {
                $this->assertSame('apple', $phone->fields['brand'] ?? null, $phone->title);
            }
            if (str_contains($phone->title, 'Samsung')) {
                $this->assertSame('samsung', $phone->fields['brand'] ?? null, $phone->title);
            }
        }

        // Field values make sense.
        $car = Ad::whereHas('category', fn ($q) => $q->where('slug', 'cars'))->first();
        $this->assertArrayHasKey('brand', $car->fields);
        $this->assertArrayHasKey('year', $car->fields);

        // Views for "Ad statistics", "Popular" and "Popular now" (PR 64): totals match the daily rows.
        $this->assertGreaterThanOrEqual(10, Ad::where('views', '>=', config('iibsom.popular_views'))->count());
        $this->assertSame((int) DB::table('ad_stats')->sum('views'), (int) Ad::sum('views'));
        $this->assertSame(0, (int) DB::table('ad_stats')->join('ads', 'ads.id', '=', 'ad_stats.ad_id')->whereNull('ads.published_at')->count());
        $this->assertSame(0, (int) DB::table('ad_stats')->where('day', '<', now('Africa/Mogadishu')->subDays(30)->toDateString())->count());

        $this->assertTrue(feature('site.demo_data'));
    }

    public function test_demo_ads_show_only_when_the_switch_is_on(): void
    {
        $this->seedDemo();
        $ad = Ad::live()->first();

        $this->get($ad->url())->assertOk();
        $this->get('/search')->assertDontSee('No ads match');

        app(FeatureManager::class)->set('site.demo_data', false);
        $this->get($ad->url())->assertNotFound();
        $this->get('/search')->assertSee('No ads match');
    }

    public function test_reset_twice_gives_the_same_amount_and_delete_keeps_real_data(): void
    {
        $real = Ad::factory()->live()->create();
        $realUser = $real->user;

        $this->artisan('iib:demo-reset', ['--force' => true, '--no-photos' => true])->assertSuccessful();
        $first = DemoData::counts();
        $this->artisan('iib:demo-reset', ['--force' => true, '--no-photos' => true])->assertSuccessful();
        $this->assertSame($first, DemoData::counts());

        DemoData::deleteAll();
        $this->assertSame(['ads' => 0, 'users' => 0, 'reports' => 0], DemoData::counts());
        $this->assertSame(0, AdminActivity::where('is_demo', true)->count());
        $this->assertNotNull($real->fresh());
        $this->assertNotNull($realUser->fresh());
    }

    /** Bug found in PR 50: "Delete all demo data" crashed when a demo user had made a (test) boost payment. */
    public function test_delete_all_works_when_demo_users_made_test_payments(): void
    {
        $this->seed();
        $demoUser = User::factory()->completed()->create(['is_demo' => true]);
        $realUser = User::factory()->completed()->create();
        $staff = User::factory()->create(['role' => 'super_admin']);
        $ad = Ad::factory()->live()->create(['user_id' => $demoUser->id, 'is_demo' => true]);
        $realAd = Ad::factory()->live()->create(['user_id' => $realUser->id]);

        $pay = function (User $user, Ad $ad, string $ref): Payment {
            $p = new Payment(['ad_id' => $ad->id, 'purpose' => 'boost', 'package' => 'top', 'method' => 'evc_plus', 'amount_cents' => 300, 'currency' => 'USD', 'reference' => $ref, 'is_test' => true]);
            $p->forceFill(['user_id' => $user->id, 'status' => 'pending'])->save();

            return $p;
        };
        Boosts::approve($pay($demoUser, $ad, 'DEMO1'), $staff->id);
        Boosts::approve($pay($realUser, $realAd, 'REAL1'), $staff->id);

        DemoData::deleteAll();

        $this->assertFalse(User::whereKey($demoUser->id)->exists());
        $this->assertSame(['REAL1'], Payment::pluck('reference')->all(), 'Only the demo test payment goes.');
        $this->assertSame([], Ledger::unbalanced(), 'The money books still balance.');
        $this->assertSame(2, DB::table('ledger_entries')->count(), 'The real payment keeps its 2 ledger lines.');
    }
}
