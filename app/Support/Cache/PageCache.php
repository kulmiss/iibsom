<?php

namespace App\Support\Cache;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Whole-page cache for visitors who are not signed in (works on shared hosting
 * too: it uses the normal cache store). Pages are kept a short time and all of
 * them are dropped at once when something changes (bump()).
 */
class PageCache
{
    public const TTL = 120; // seconds

    private const VERSION_KEY = 'page-cache:version';

    /** Any change that shows on public pages calls this (ads, catalogue, switches, settings, pages, banners). */
    public static function bump(): void
    {
        rescue(fn () => Cache::forever(self::VERSION_KEY, (string) hrtime(true)), report: false);
    }

    public static function key(Request $request): string
    {
        $version = Cache::get(self::VERSION_KEY, '0');

        return 'page:'.$version.':'.hash('sha256', $request->getSchemeAndHttpHost().$request->getRequestUri());
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
