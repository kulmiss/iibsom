<?php

namespace Tests\Feature\Admin;

use App\Models\Ad;
use App\Models\User;
use App\Support\Features\FeatureManager;

class AdminPermissionsTest extends AdminTestCase
{
    public function test_roles_get_their_permissions(): void
    {
        $this->assertContains('features.manage', $this->staff('super_admin')->permissions());
        $this->assertContains('ads.moderate', $this->staff('moderator')->permissions());
        $this->assertNotContains('settings.manage', $this->staff('moderator')->permissions());
        $this->assertNotContains('ads.moderate', $this->staff('support')->permissions());
        $this->assertSame([], User::factory()->create()->permissions());
    }

    public function test_support_cannot_change_settings(): void
    {
        $this->asStaff('support');

        $this->putJson('/admin/api/settings/appearance', ['brand_color' => '#ff0000'])->assertForbidden();
        $this->getJson('/admin/api/activity')->assertForbidden();
    }

    public function test_dashboard_numbers(): void
    {
        $this->asStaff('support');
        Ad::factory()->count(2)->create();
        Ad::factory()->live()->create();
        $demo = Ad::factory()->live()->create();
        $demo->forceFill(['is_demo' => true])->save();

        $data = $this->getJson('/admin/api/dashboard')->assertOk()->json();

        $this->assertSame(3, $data['cards']['ads_today']); // demo left out
        $this->assertSame(2, $data['cards']['ads_pending']);
        $this->assertSame(1, $data['cards']['ads_live']);
        $this->assertCount(90, $data['chart']);
        $this->assertFalse($data['includes_demo']);
        $this->assertNotEmpty($data['top_categories']);
        $this->assertSame('Mogadishu', $data['top_cities'][0]['name']);
    }

    public function test_dashboard_includes_demo_when_switched_on(): void
    {
        $this->asStaff();
        $demo = Ad::factory()->create();
        $demo->forceFill(['is_demo' => true])->save();
        app(FeatureManager::class)->set('site.demo_data', true);

        $this->getJson('/admin/api/dashboard')->assertJsonPath('cards.ads_today', 1)->assertJsonPath('includes_demo', true);
    }

    public function test_brand_colour_is_saved_and_logged(): void
    {
        $this->asStaff();

        $this->putJson('/admin/api/settings/appearance', ['brand_color' => '#AA3300'])->assertOk()->assertJson(['brand_color' => '#aa3300']);
        $this->putJson('/admin/api/settings/appearance', ['brand_color' => 'red; }'])->assertUnprocessable();

        $this->getJson('/admin/api/me')->assertJsonPath('user.brand_color', '#aa3300');
        $this->getJson('/admin/api/activity')->assertOk()->assertJsonPath('data.0.action', 'settings.brand_color');
    }

    public function test_unknown_api_route_is_json_404(): void
    {
        $this->asStaff();
        $this->getJson('/admin/api/nope')->assertNotFound()->assertJson(['message' => 'Not found.']);
    }

    public function test_spa_page_gets_csp_nonce(): void
    {
        @mkdir(public_path('admin-app'), 0755, true);
        $existing = @file_get_contents(public_path('admin-app/index.html'));
        file_put_contents(public_path('admin-app/index.html'), '<html><head><script type="module" src="/admin/assets/a.js"></script><link rel="modulepreload" href="/admin/assets/b.js"></head></html>');

        try {
            $res = $this->get('/admin/ads/123')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
            preg_match("/'nonce-([^']+)'/", $res->headers->get('Content-Security-Policy'), $m);
            $this->assertStringContainsString('<script nonce="'.$m[1].'" type="module"', $res->getContent());
            $this->assertStringContainsString('<link nonce="'.$m[1].'"', $res->getContent());
        } finally {
            $existing === false ? @unlink(public_path('admin-app/index.html')) : file_put_contents(public_path('admin-app/index.html'), $existing);
        }
    }
}
