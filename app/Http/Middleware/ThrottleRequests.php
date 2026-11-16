<?php

namespace App\Http\Middleware;

use App\Support\Privacy\Ip;
use Illuminate\Routing\Middleware\ThrottleRequests as BaseThrottle;
use RuntimeException;

/**
 * Laravel's `throttle`, with two changes:
 *  - each limited page has its OWN counter (Laravel shares one counter per user
 *    across all pages, so 12 photo uploads used up the 10-per-minute posting limit);
 *  - guests are counted by a keyed hash of the IP, never the IP itself.
 */
class ThrottleRequests extends BaseThrottle
{
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();
        if (! $route) {
            throw new RuntimeException('Unable to generate the request signature. Route unavailable.');
        }
        // Same page in another language (/so/...) shares the counter: no doubling the limit by switching language.
        $uri = preg_replace('#^\{locale\}/#', '', $route->uri()) ?? $route->uri();
        $page = $route->getDomain().'|'.implode(',', $route->methods()).'|'.$uri;

        $who = ($user = $request->user()) ? 'user:'.$user->getAuthIdentifier() : 'guest:'.Ip::key($request);

        return sha1($page.'|'.$who);
    }
}
