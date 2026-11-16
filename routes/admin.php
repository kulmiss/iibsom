<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\AppearanceController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SpaController;
use Illuminate\Support\Facades\Route;

/*
| Super Admin. JSON API under /admin/api, the React app for every other /admin/* URL.
| Loaded with the "web" middleware (session + CSRF) in bootstrap/app.php.
*/

Route::prefix('admin/api')->name('admin.api.')->group(function () {
    // Sign-in steps (not signed in yet).
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->name('login');
        Route::get('/2fa/setup', [AuthController::class, 'setup'])->name('2fa.setup');
        Route::post('/2fa/confirm', [AuthController::class, 'confirm'])->name('2fa.confirm');
        Route::post('/2fa/verify', [AuthController::class, 'verify'])->name('2fa.verify');
    });

    Route::middleware('admin')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('/dashboard', DashboardController::class)->middleware('admin.can:dashboard.view')->name('dashboard');
        Route::get('/activity', ActivityController::class)->middleware('admin.can:activity.view')->name('activity');

        Route::get('/settings/appearance', [AppearanceController::class, 'show'])->name('appearance');
        Route::put('/settings/appearance', [AppearanceController::class, 'update'])->middleware('admin.can:settings.manage')->name('appearance.update');
    });

    Route::any('/{any}', fn () => response()->json(['message' => 'Not found.'], 404))->where('any', '.*');
});

Route::get('/admin/{path?}', SpaController::class)->where('path', '.*')->name('admin.app');
