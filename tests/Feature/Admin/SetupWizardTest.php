<?php

namespace Tests\Feature\Admin;

use App\Models\City;
use App\Models\Region;
use App\Support\Catalog;
use App\Support\Features\FeatureManager;

class SetupWizardTest extends AdminTestCase
{
    /** @return array<string, mixed> */
    private function answers(array $cities): array
    {
        return [
            'languages' => ['en', 'so'],
            'support_email' => 'help@iibsom.test',
            'ads_expiry_days' => 30,
            'ads_max_per_day' => 4,
            'ads_max_photos' => 6,
            'cities' => $cities,
            'weekly_emails' => ['owner@iibsom.test'],
            'demo_data' => false,
        ];
    }

    public function test_super_admin_is_asked_once(): void
    {
        $this->asStaff();
        $this->getJson('/admin/api/me')->assertOk()->assertJsonPath('user.setup_needed', true);

        $mogadishu = City::where('slug', 'mogadishu')->firstOrFail();
        $this->getJson('/admin/api/setup')->assertOk()
            ->assertJsonPath('completed', false)
            ->assertJsonPath('settings.ads_expiry_days', 60)
            ->assertJsonFragment(['id' => $mogadishu->id, 'name' => 'Mogadishu', 'enabled' => true]);

        $this->postJson('/admin/api/setup', $this->answers([$mogadishu->id]))->assertOk()->assertJsonPath('completed', true);

        $this->getJson('/admin/api/me')->assertJsonPath('user.setup_needed', false);
        $this->assertSame(30, setting('ads.expiry_days'));
        $this->assertSame(4, setting('ads.max_per_day'));
        $this->assertSame(6, setting('ads.max_photos'));
        $this->assertSame(['en', 'so'], setting('languages.enabled'));
        $this->assertSame('help@iibsom.test', setting('site.support_email'));
        $this->assertSame(['owner@iibsom.test'], setting('reports.weekly_emails'));
        $this->assertFalse(feature('site.demo_data'));
        // Only the chosen city is ON.
        $this->assertSame([$mogadishu->id], City::where('enabled', true)->pluck('id')->all());
        $this->assertDatabaseHas('admin_activities', ['action' => 'setup.finished']);
    }

    public function test_choosing_a_city_turns_its_region_on(): void
    {
        $this->asStaff();
        $city = City::where('slug', 'kismayo')->firstOrFail();
        $city->region->forceFill(['enabled' => false])->save();

        $this->postJson('/admin/api/setup', $this->answers([$city->id]))->assertOk();

        $this->assertTrue((bool) $city->fresh()?->enabled);
        $this->assertTrue((bool) Region::find($city->region_id)?->enabled);
        $this->assertContains('kismayo', array_column(app(Catalog::class)->cities(), 'slug'));
    }

    public function test_bad_answers_change_nothing(): void
    {
        $this->asStaff();
        $before = City::where('enabled', true)->pluck('id')->all();

        $this->postJson('/admin/api/setup', ['cities' => []] + $this->answers([]))
            ->assertUnprocessable()->assertJsonPath('errors.cities.0', 'Choose at least one city.');
        $this->postJson('/admin/api/setup', ['ads_expiry_days' => 2] + $this->answers([999999]))
            ->assertUnprocessable()->assertJsonValidationErrors(['ads_expiry_days', 'cities.0']);

        $this->assertSame(60, setting('ads.expiry_days'));
        $this->assertSame($before, City::where('enabled', true)->pluck('id')->all());
        $this->getJson('/admin/api/me')->assertJsonPath('user.setup_needed', true);
    }

    public function test_skip_stops_it_opening_by_itself(): void
    {
        $this->asStaff();

        $this->postJson('/admin/api/setup/skip')->assertOk();

        $this->getJson('/admin/api/me')->assertJsonPath('user.setup_needed', false);
        $this->getJson('/admin/api/setup')->assertOk()->assertJsonPath('completed', true);
        $this->assertSame(60, setting('ads.expiry_days'));
    }

    public function test_only_the_super_admin_can_use_it(): void
    {
        foreach (['moderator', 'support'] as $role) {
            $this->asStaff($role);
            $this->getJson('/admin/api/me')->assertJsonPath('user.setup_needed', false);
            $this->getJson('/admin/api/setup')->assertForbidden();
            $this->postJson('/admin/api/setup/skip')->assertForbidden();
        }
        auth()->logout();
        $this->postJson('/admin/api/setup', $this->answers([1]))->assertUnauthorized();
    }

    public function test_switch_off(): void
    {
        app(FeatureManager::class)->set('admin.setup_wizard', false);
        $this->asStaff();

        $this->getJson('/admin/api/me')->assertJsonPath('user.setup_needed', false);
        $this->getJson('/admin/api/setup')->assertNotFound();
    }
}
