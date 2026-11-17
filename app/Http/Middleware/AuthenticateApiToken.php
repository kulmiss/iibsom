<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Authorization: Bearer iib_..." for the write API. Usage: api.token:read or api.token:write.
 * No session, no cookie. Banned users and users without a complete profile are refused.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next, string $ability = 'read'): Response
    {
        $token = ApiToken::findByKey((string) $request->bearerToken());
        if ($token === null) {
            return response()->json(['message' => 'Missing, wrong or expired API token.'], 401, ['WWW-Authenticate' => 'Bearer']);
        }
        if (! $token->can($ability)) {
            return response()->json(['message' => "This token cannot {$ability}."], 403);
        }
        $user = $token->user;
        if ($user->isBanned()) {
            return response()->json(['message' => __('account.login.banned')], 403);
        }
        if ($ability === 'write' && ! $user->hasCompletedProfile()) {
            return response()->json(['message' => 'Finish your profile on the website first (name, city, phone).'], 403);
        }

        Auth::guard('web')->setUser($user);
        $request->setUserResolver(fn () => $user);
        if ($token->last_used_at === null || $token->last_used_at->lt(now()->subMinutes(5))) {
            $token->forceFill(['last_used_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
