<?php

namespace Tests\Feature;

use App\Support\Features\FeatureManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_manifest_is_complete(): void
    {
        $m = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);

        $this->assertSame('iibsom', $m['short_name']);
        $this->assertSame('standalone', $m['display']);
        $this->assertSame('/', $m['scope']);
        $this->assertContains('maskable', array_column($m['icons'], 'purpose'));
        foreach ($m['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
            [$w, $h] = getimagesize(public_path(ltrim($icon['src'], '/'))) ?: [0, 0];
            $this->assertSame($icon['sizes'], "{$w}x{$h}", $icon['src']);
        }
    }

    public function test_pages_link_the_manifest_only_when_on(): void
    {
        $this->get('/')->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)->assertSee('data-pwa="on"', false);

        app(FeatureManager::class)->set('pwa', false);
        $this->get('/')->assertDontSee('manifest.webmanifest', false)->assertSee('data-pwa="off"', false); // the script then removes the worker
    }

    public function test_service_worker_never_stores_pages(): void
    {
        $sw = (string) file_get_contents(public_path('sw.js'));

        // Pages come from the network; offline shows the offline page, never a stored page.
        $this->assertStringContainsString("if (req.mode === 'navigate') {\n        event.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));", $sw);
        // Only GET, and only our static files and ad photos are stored.
        $this->assertStringContainsString("if (req.method !== 'GET') return;", $sw);
        $this->assertSame(2, substr_count($sw, 'c.put(req') + substr_count($sw, 'cache.put(req'));
        $this->assertFileExists(public_path('offline.html'));
    }

    public function test_offline_page_is_in_both_languages(): void
    {
        $html = (string) file_get_contents(public_path('offline.html'));
        $this->assertStringContainsString('You are offline', $html);
        $this->assertStringContainsString('Internet ma jiro', $html);
        $this->assertStringNotContainsString('<script', $html); // works under any CSP
    }
}
