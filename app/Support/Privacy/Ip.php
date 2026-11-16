<?php

namespace App\Support\Privacy;

use Illuminate\Http\Request;

/**
 * Rule: we never store the IP address of visitors who are not signed in.
 * Rate limits still need "the same visitor", so they use a keyed hash of the IP
 * (HMAC with APP_KEY): it can't be turned back into the IP, and it lives only
 * a few minutes in the cache.
 */
class Ip
{
    public static function key(Request $request): string
    {
        return hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));
    }
}
