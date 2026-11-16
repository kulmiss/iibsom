<?php

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\AdminCan;
use App\Http\Middleware\ApiLocale;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureProfileComplete;
use App\Http\Middleware\GuestPages;
use App\Http\Middleware\LogoutBannedUsers;
use App\Http\Middleware\RejectInvalidUtf8;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\ThrottleRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [__DIR__.'/../routes/admin.php', __DIR__.'/../routes/web.php'],
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Cloudflare / a load balancer: trust its X-Forwarded-* headers (HTTPS, real host).
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // Broken characters → 400, for the website and the API.
        $middleware->prepend(RejectInvalidUtf8::class);

        // Guests: no session saved, no cookies, page cache. Must run before the session starts.
        $middleware->web(prepend: [GuestPages::class]);

        $middleware->web(append: [
            SecurityHeaders::class,
            LogoutBannedUsers::class,
        ]);

        // Not signed in → go to the sign-in page (in the current language).
        $middleware->redirectGuestsTo(fn () => lroute('login'));
        $middleware->redirectUsersTo(fn () => lroute('home'));

        $middleware->alias([
            'feature' => EnsureFeatureEnabled::class,
            'locale' => SetLocale::class,
            'profile.complete' => EnsureProfileComplete::class,
            'admin' => AdminAuthenticate::class,
            'admin.can' => AdminCan::class,
            'throttle' => ThrottleRequests::class, // guests counted by hashed IP
            'api.locale' => ApiLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
