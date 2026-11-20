<?php

namespace App\Support\Cache;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Whole-page cache for visitors who are not signed in (works on shared hosting
 * too: it uses the normal cache store). Pages are kept a short time (TTL).
 *
 * Three "versions" decide when kept pages are dropped (PR 90, so a busy site keeps its cache):
 *  - site:  everything (settings, switches, categories, pages, banners, a ban…): bump().
 *  - lists: pages that list ads (home, categories, search, sellers, shops). An ad change refreshes them at
 *           most once every few seconds (config "iibsom.page_cache_list_delay", 30 by default): the first
 *           change at once, later ones together when the wait is over.
 *  - one ad: its own page, at once on every change of that ad (adChanged()).
 * Pages without ads (help, contact, info pages) follow only the site version. An ad page follows the site
 * and its own version; its "similar ads" may be up to TTL old.
 */
class PageCache
{
    public const TTL = 120; // seconds

    private const VERSION_KEY = 'page-cache:version';

    private const LISTS_KEY = 'page-cache:lists';

    private const LISTS_LOCK = 'page-cache:lists-lock';

    private const LISTS_PENDING = 'page-cache:lists-pending';

    /** Routes whose pages show no ads: an ad change never drops them. */
    private const NO_ADS = ['page', 'help', 'help.show', 'contact'];

    /** Any change that shows on public pages everywhere calls this (catalogue, switches, settings, pages, banners). */
    public static function bump(): void
    {
        rescue(fn () => Cache::forever(self::VERSION_KEY, (string) hrtime(true)), report: false);
    }

    /** An ad was saved or deleted: its own page now, the lists soon (see the class note). */
    public static function adChanged(int $adId): void
    {
        rescue(fn () => Cache::forever('page-cache:ad:'.$adId, (string) hrtime(true)), report: false);
        self::listsChanged();
    }

    /** Something in the ad lists changed (an ad, the live-ad counts): refreshed at once or after the wait. */
    public static function listsChanged(): void
    {
        rescue(function () {
            $delay = (int) config('iibsom.page_cache_list_delay', 30);
            if ($delay <= 0 || Cache::add(self::LISTS_LOCK, 1, $delay)) {
                Cache::forever(self::LISTS_KEY, (string) hrtime(true));
                Cache::forget(self::LISTS_PENDING);
            } else {
                Cache::forever(self::LISTS_PENDING, 1); // applied when the wait is over (lists())
            }
        }, report: false);
    }

    /** The lists version; a waiting ad change is applied here once the wait is over. */
    private static function lists(): string
    {
        if (Cache::get(self::LISTS_PENDING)) {
            $delay = (int) config('iibsom.page_cache_list_delay', 30);
            if ($delay <= 0 || Cache::add(self::LISTS_LOCK, 1, $delay)) {
                Cache::forever(self::LISTS_KEY, (string) hrtime(true));
                Cache::forget(self::LISTS_PENDING);
            }
        }

        return (string) Cache::get(self::LISTS_KEY, '0');
    }

    /** Changes with every site bump and every (batched) ad change: for other caches made from ads or settings. */
    public static function version(): string
    {
        return (string) rescue(fn () => Cache::get(self::VERSION_KEY, '0').'.'.self::lists(), '0', report: false);
    }

    public static function key(Request $request): string
    {
        $route = $request->route();
        $name = Str::after((string) (is_object($route) ? $route->getName() : ''), 'loc.');
        $version = (string) Cache::get(self::VERSION_KEY, '0');
        $version .= match (true) {
            in_array($name, self::NO_ADS, true) => '',
            $name === 'ad.show' => '.ad'.self::adVersion($request),
            default => '.'.self::lists(),
        };
        // New CSS/JS files after an update get new names: a page kept from before
        // would load files that are gone (a page with no styles). So a new build = new keys.
        $build = Vite::manifestHash() ?? 'no-build';

        return 'page:'.$version.':'.$build.':'.hash('sha256', $request->getSchemeAndHttpHost().$request->getRequestUri());
    }

    /** The ad id is the number at the end of the address ("...-123"). */
    private static function adVersion(Request $request): string
    {
        $route = $request->route();
        $param = is_object($route) ? $route->parameter('slugId', '') : '';
        $slugId = is_string($param) ? $param : '';
        $id = preg_match('/-(\d+)$/', $slugId, $m) ? $m[1] : '0';

        return $id.'-'.Cache::get('page-cache:ad:'.$id, '0');
    }

    public static function get(Request $request): ?Response
    {
        $hit = rescue(fn () => Cache::get(self::key($request)), report: false);
        if (! is_array($hit)) {
            return null;
        }

        // Fresh CSP nonce for every visitor, even from the cache.
        $body = $hit['body'];
        $headers = $hit['headers'];
        if ($old = $hit['nonce'] ?? null) {
            $new = Str::random(32);
            Vite::useCspNonce($new);
            $body = str_replace($old, $new, $body);
            $headers['content-security-policy'] = array_map(fn ($v) => str_replace($old, $new, $v), $headers['content-security-policy'] ?? []);
        }

        $response = new Response($body, 200, $headers);
        $response->headers->set('X-Page-Cache', 'HIT');

        return $response;
    }

    public static function put(Request $request, Response $response): void
    {
        $headers = collect($response->headers->all())
            ->except(['set-cookie', 'date', 'x-page-cache'])
            ->all();

        preg_match("/'nonce-([A-Za-z0-9]{32})'/", (string) $response->headers->get('Content-Security-Policy'), $m);

        rescue(fn () => Cache::put(self::key($request), [
            'body' => $response->getContent(),
            'headers' => $headers,
            'nonce' => $m[1] ?? null,
        ], self::TTL), report: false);
    }
}
