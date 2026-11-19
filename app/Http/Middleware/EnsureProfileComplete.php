<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Posting (and the rest of the account pages) need name, city, phone and accepted Terms first. Reporting does not (PR 81). */
class EnsureProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->hasCompletedProfile()) {
            if ($request->isMethod('GET')) {
                redirect()->setIntendedUrl($request->fullUrl());
            }

            return redirect(lroute('welcome'));
        }

        return $next($request);
    }
}
