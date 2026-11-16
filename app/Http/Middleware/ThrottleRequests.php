<?php

namespace App\Http\Middleware;

use App\Support\Privacy\Ip;
use Illuminate\Routing\Middleware\ThrottleRequests as BaseThrottle;
use RuntimeException;

/** Same as Laravel's `throttle`, but guests are counted by a hashed IP (never the IP itself). */
class ThrottleRequests extends BaseThrottle
{
    protected function resolveRequestSignature($request)
    {
        if ($user = $request->user()) {
            return sha1((string) $user->getAuthIdentifier());
        }
        if ($route = $request->route()) {
            return sha1($route->getDomain().'|'.Ip::key($request));
        }

        throw new RuntimeException('Unable to generate the request signature. Route unavailable.');
    }
}
