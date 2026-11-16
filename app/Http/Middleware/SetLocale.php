<?php

namespace App\Http\Middleware;

use App\Support\Localization\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The URL decides the language (/so/... = Somali). This keeps every page
 * cacheable by URL. The visitor's choice is remembered in a cookie by the
 * browser script, which then swaps the page in place (no reload).
 */
class SetLocale
{
    public function __construct(private readonly Locales $locales) {}

    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->route('locale') ?? $this->locales->default();

        // Language is OFF (or unknown) → page does not exist.
        abort_unless($this->locales->isEnabled($code), 404);

        app()->setLocale($code);

        // Route parameter is only for the URL; controllers don't need it.
        $request->route()?->forgetParameter('locale');

        return $next($request);
    }
}
