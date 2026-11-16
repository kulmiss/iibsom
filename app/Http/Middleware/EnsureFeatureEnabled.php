<?php

namespace App\Http\Middleware;

use App\Support\Features\FeatureManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: ->middleware('feature:chat')
 * Feature OFF = 404, so the page looks like it never existed.
 */
class EnsureFeatureEnabled
{
    public function __construct(private readonly FeatureManager $features) {}

    public function handle(Request $request, Closure $next, string ...$keys): Response
    {
        foreach ($keys as $key) {
            abort_if($this->features->disabled($key), 404);
        }

        return $next($request);
    }
}
