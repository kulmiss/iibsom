<?php

namespace Tests\Feature;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\AdminActivity;
use App\Models\Report;
use App\Models\User;
use App\Support\Demo\DemoData;
use App\Support\Features\FeatureManager;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        // Field values make sense.
        $car = Ad::whereHas('category', fn ($q) => $q->where('slug', 'cars'))->first();
        $this->assertArrayHasKey('brand', $car->fields);
        $this->assertArrayHasKey('year', $car->fields);

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
}
