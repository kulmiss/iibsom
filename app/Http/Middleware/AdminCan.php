<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** ->middleware('admin.can:ads.moderate') */
class AdminCan
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        foreach ($permissions as $permission) {
            if (! $request->user()?->hasPermission($permission)) {
                return response()->json(['message' => 'You do not have permission to do this.'], 403);
            }
        }

        return $next($request);
    }
}
