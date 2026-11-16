<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin API guard: signed in through the ADMIN login (password + 2FA),
 * staff role, not banned, and active within the idle timeout.
 */
class AdminAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $session = $request->session();

        $ok = $user
            && $user->isStaff()
            && ! $user->isBanned()
            && $user->hasTwoFactor()
            && $session->get('admin.2fa_passed') === $user->id;

        $idle = now()->getTimestamp() - (int) $session->get('admin.last_seen', 0);
        if ($ok && $idle > config('admin.idle_timeout_minutes') * 60) {
            $ok = false;
        }

        if (! $ok) {
            if ($user && $session->has('admin.2fa_passed')) {
                Auth::logout();
                $session->invalidate();
                $session->regenerateToken();
            }

            return response()->json(['message' => 'Please sign in.'], 401);
        }

        $session->put('admin.last_seen', now()->getTimestamp());

        return $next($request);
    }
}
