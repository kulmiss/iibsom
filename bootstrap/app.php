<?php

use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\AdminCan;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureProfileComplete;
use App\Http\Middleware\LogoutBannedUsers;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [__DIR__.'/../routes/admin.php', __DIR__.'/../routes/web.php'],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
