<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_send_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeaderMissing('X-Powered-By');
    }

    public function test_csp_is_strict_with_a_nonce(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9]{32}'/", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
    }

    public function test_photos_host_is_allowed_for_images(): void
    {
        config(['iibsom.photos_disk' => 'r2', 'filesystems.disks.r2.url' => 'https://photos.iibsom.com/']);

        $this->assertStringContainsString("img-src 'self' data: blob: https://photos.iibsom.com", $this->get('/')->headers->get('Content-Security-Policy'));
    }

    public function test_nonce_changes_every_request(): void
    {
        $a = $this->get('/')->headers->get('Content-Security-Policy');
        $b = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertNotSame($a, $b);
    }

    public function test_hsts_only_on_https(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security');
    }
}
