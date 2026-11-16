<?php

namespace App\Http\Middleware;

use App\Support\Localization\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** API language: ?lang=so (only languages that are ON), else the default. */
class ApiLocale
{
    public function __construct(private readonly Locales $locales) {}

    public function handle(Request $request, Closure $next): Response
    {
        $lang = (string) $request->query('lang', $this->locales->default());
        app()->setLocale($this->locales->isEnabled($lang) ? $lang : $this->locales->default());

        $response = $next($request);
        $response->headers->set('Content-Language', app()->getLocale());
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
