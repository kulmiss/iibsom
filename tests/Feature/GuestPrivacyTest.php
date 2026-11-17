<?php

namespace Tests\Feature;

use App\Http\Middleware\ThrottleRequests;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Cache\PageCache;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/** No IP storage for guests, cookie-free cacheable guest pages, page cache. */
class GuestPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function nonce($response): string
    {
        preg_match("/'nonce-([A-Za-z0-9]{32})'/", $response->headers->get('Content-Security-Policy'), $m);

        return $m[1];
    }

    public function test_guest_pages_send_no_cookies_and_can_be_cached(): void
    {
        $first = $this->get('/')->assertOk()->assertHeader('X-Page-Cache', 'MISS');
        $this->assertSame([], $first->headers->getCookies());
        $this->assertStringContainsString('public', $first->headers->get('Cache-Control'));

        $second = $this->get('/')->assertOk()->assertHeader('X-Page-Cache', 'HIT');
        $this->assertSame([], $second->headers->getCookies());

        // Fresh CSP nonce every time, and the page uses it.
        $this->assertNotSame($this->nonce($first), $this->nonce($second));
        $this->assertStringNotContainsString($this->nonce($first), $second->getContent());
    }

    /** Security audit D22: bots often send HEAD; it must not start a session (a database row) either. */
    public function test_head_requests_are_cookie_free_and_never_empty_the_cache(): void
    {
        config(['session.driver' => 'database']);
        $this->app['session']->forgetDrivers();

        $head = $this->call('HEAD', '/');
        $head->assertOk();
        $this->assertSame([], $head->headers->getCookies());
        $this->assertSame(0, DB::table('sessions')->count());

        // A HEAD answer has no body: it must never be kept as the page for everyone.
        $get = $this->get('/')->assertOk();
        $this->assertStringContainsString('</html>', (string) $get->getContent());
        $this->assertStringContainsString('</html>', (string) $this->get('/')->getContent());
    }

    public function test_cached_page_gets_the_new_nonce_in_its_html(): void
    {
        $html = '<script nonce="'.str_repeat('a', 32).'"></script>';
        $request = Request::create('/');
        $response = new Response($html, 200, ['Content-Security-Policy' => "script-src 'nonce-".str_repeat('a', 32)."'"]);
        PageCache::put($request, $response);

        $hit = PageCache::get($request);
        preg_match("/'nonce-([A-Za-z0-9]{32})'/", $hit->headers->get('Content-Security-Policy'), $m);
        $this->assertNotSame(str_repeat('a', 32), $m[1]);
        $this->assertSame('<script nonce="'.$m[1].'"></script>', $hit->getContent());
    }

    public function test_filtered_pages_are_cookie_free_but_not_kept_on_the_server(): void
    {
        $res = $this->get('/search?q=toyota')->assertOk();
        $this->assertSame([], $res->headers->getCookies());
        $this->assertFalse($res->headers->has('X-Page-Cache'));
    }

    public function test_signed_in_users_get_normal_pages(): void
    {
        $user = User::factory()->completed()->create();
        $this->get('/'); // fill the cache as a guest

        $res = $this->actingAs($user)->withCookie(config('session.cookie'), 'x')->get('/')->assertOk();
        $this->assertFalse($res->headers->has('X-Page-Cache'));
        $this->assertStringNotContainsString('public', (string) $res->headers->get('Cache-Control'));
    }

    public function test_any_change_clears_the_page_cache(): void
    {
        $this->get('/')->assertHeader('X-Page-Cache', 'MISS');
        $this->get('/')->assertHeader('X-Page-Cache', 'HIT');

        Ad::factory()->live()->create([
            'title' => 'Brand new approved ad',
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);
        $this->get('/')->assertHeader('X-Page-Cache', 'MISS')->assertSee('Brand new approved ad');

        app(FeatureManager::class)->set('site.banners', false);
        $this->get('/')->assertHeader('X-Page-Cache', 'MISS');
    }

    public function test_new_css_and_js_build_starts_a_fresh_page_cache(): void
    {
        $vite = new class extends Vite
        {
            public ?string $hash = 'build-1';

            public function manifestHash($buildDirectory = null)
            {
                return $this->hash;
            }
        };
        $this->swap(Vite::class, $vite);

        $this->get('/')->assertHeader('X-Page-Cache', 'MISS');
        $this->get('/')->assertHeader('X-Page-Cache', 'HIT');

        // After an update the old page would point to CSS/JS files that no longer exist.
        $vite->hash = 'build-2';
        $this->get('/')->assertHeader('X-Page-Cache', 'MISS');
    }

    public function test_page_cache_switch(): void
    {
        app(FeatureManager::class)->set('performance.page_cache', false);
        $this->get('/');
        $res = $this->get('/');
        $this->assertFalse($res->headers->has('X-Page-Cache'));
        $this->assertSame([], $res->headers->getCookies()); // still cookie-free
    }

    public function test_database_sessions_keep_no_ip_for_guests(): void
    {
        config(['session.driver' => 'database']);
        $this->app['session']->forgetDrivers();

        $this->get('/login')->assertOk(); // login page starts a session
        $row = DB::table('sessions')->first();
        $this->assertNotNull($row);
        $this->assertNull($row->ip_address);
        $this->assertNull($row->user_agent);
        $this->assertNull($row->user_id);
    }

    public function test_rate_limits_never_use_the_raw_ip(): void
    {
        $request = Request::create('/search', 'GET', server: ['REMOTE_ADDR' => '197.157.1.2']);
        $request->setRouteResolver(fn () => app('router')->getRoutes()->match($request));

        $method = new \ReflectionMethod(ThrottleRequests::class, 'resolveRequestSignature');
        $signature = $method->invoke(app(ThrottleRequests::class), $request);

        $this->assertStringNotContainsString('197.157.1.2', $signature);
        $this->assertNotSame(sha1('|197.157.1.2'), $signature); // not a plain hash that could be guessed back
    }

    public function test_each_page_has_its_own_limit_but_languages_share_it(): void
    {
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        for ($i = 0; $i < 15; $i++) {
            $this->getJson('/ads/999999/contact')->assertNotFound();
            $this->getJson('/so/ads/999999/contact')->assertNotFound();
        }
        $this->getJson('/ads/999999/contact')->assertStatus(429);   // 30 per minute, both languages together
        $this->getJson('/api/v1/cities')->assertOk();                // another page: its own counter
    }
}
