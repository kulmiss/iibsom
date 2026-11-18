<?php

namespace App\Http\Middleware;

use App\Support\Cache\PageCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public pages for visitors who are not signed in:
 *  - no session is saved and no cookies are sent (nothing stored about them,
 *    and Cloudflare / browsers may cache the page),
 *  - optional whole-page cache on the server (switch "performance.page_cache").
 * Anyone with a session (signed in, or in the middle of signing in) gets the
 * normal, uncached page.
 */
class GuestPages
{
    /** Route names (without the "loc." language prefix) that are the same for every guest. */
    public const CACHEABLE = ['home', 'search', 'browse.category', 'browse.brand', 'ad.show', 'page', 'help', 'help.show', 'shop.show', 'seller.show', 'seller.reviews'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isGuestPage($request)) {
            return $next($request);
        }

        config(['session.driver' => 'array']); // nothing is written for guests

        // HEAD answers have no body, so they are never read from or put in the server cache.
        $useCache = feature('performance.page_cache') && $request->isMethod('GET') && $this->serverCacheable($request);
        if ($useCache && ($hit = PageCache::get($request))) {
            return $hit;
        }

        $response = $next($request);

        foreach ($response->headers->getCookies() as $cookie) {
            $response->headers->removeCookie($cookie->getName(), $cookie->getPath(), $cookie->getDomain());
        }

        if ($response->getStatusCode() === 200 && ! $request->user()) {
            $response->headers->set('Cache-Control', 'public, max-age=60, s-maxage='.PageCache::TTL);
            if ($useCache) {
                $response->headers->set('X-Page-Cache', 'MISS');
                PageCache::put($request, $response);
            }
        }

        return $response;
    }

    private function isGuestPage(Request $request): bool
    {
        // HEAD too: bots send it a lot, and it must not start a session (a database row) either.
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true) || $request->hasHeader('Authorization')) {
            return false;
        }

        $name = Str::after((string) $request->route()?->getName(), 'loc.');
        if (! in_array($name, self::CACHEABLE, true)) {
            return false;
        }

        // A session or "remember me" cookie means a signed-in user (or one signing in).
        foreach (array_keys($request->cookies->all()) as $cookie) {
            if ($cookie === config('session.cookie') || str_starts_with($cookie, 'remember_')) {
                return false;
            }
        }

        return true;
    }

    /** Only clean URLs (no filters) go in the server cache, so it can't be filled with junk. */
    private function serverCacheable(Request $request): bool
    {
        return array_diff(array_keys($request->query()), ['page']) === [];
    }
}
