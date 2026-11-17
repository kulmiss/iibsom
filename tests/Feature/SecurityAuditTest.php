<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteItem;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Checks from the security audit (SECURITY_REPORT.md, D22) that keep holding as the code grows. */
class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    /** The private disk (ID photos, receipts, uploads waiting for processing) must have no web address at all. */
    public function test_private_files_have_no_web_route(): void
    {
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $storageRoutes = collect(Route::getRoutes()->getRoutes())->filter(fn (RouteItem $r) => str_starts_with($r->uri(), 'storage/'));
        $this->assertSame([], $storageRoutes->map(fn (RouteItem $r) => $r->uri())->values()->all());
        $this->put('/storage/verifications/x.jpg')->assertNotFound();
    }

    /** Every customer route that changes something has a rate limit (admin API: own limit + permissions). */
    public function test_every_changing_customer_route_has_a_rate_limit(): void
    {
        $missing = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RouteItem $r) => array_intersect($r->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [])
            ->reject(fn (RouteItem $r) => str_starts_with($r->uri(), 'admin/api'))
            ->reject(fn (RouteItem $r) => collect($r->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'throttle')))
            ->map(fn (RouteItem $r) => implode('|', $r->methods()).' '.$r->uri())->values()->all();

        $this->assertSame([], $missing);
    }

    /** Every admin API route needs a signed-in staff member (except the sign-in steps themselves). */
    public function test_every_admin_api_route_needs_staff(): void
    {
        $open = ['admin/api/login', 'admin/api/session', 'admin/api/2fa/setup', 'admin/api/2fa/confirm', 'admin/api/2fa/verify', 'admin/api/{any}'];
        $missing = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RouteItem $r) => str_starts_with($r->uri(), 'admin/api'))
            ->reject(fn (RouteItem $r) => in_array($r->uri(), $open, true))
            ->reject(fn (RouteItem $r) => in_array('admin', $r->gatherMiddleware(), true))
            ->map(fn (RouteItem $r) => $r->uri())->values()->all();

        $this->assertSame([], $missing);
    }

    /** Typical attack inputs answer normally: no raw script in pages, no database errors, no file reads. */
    public function test_attack_inputs_are_harmless(): void
    {
        $this->seed();
        $xss = '<script>alert(1)</script>';
        $this->get('/search?q='.urlencode($xss))->assertOk()->assertDontSee($xss, false);
        $this->get('/search?q='.urlencode("' OR 1=1 -- "))->assertOk();
        $this->getJson('/api/v1/ads?q='.urlencode('" OR ""="'))->assertOk();
        $this->get('/help/'.urlencode('../../.env'))->assertNotFound();
        $this->get('/cars?sort='.urlencode('price;DROP TABLE ads'))->assertRedirect(); // refused by validation, no SQL
        $this->get('/.env')->assertNotFound();
    }
}
