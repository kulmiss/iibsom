<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\AdController;
use App\Http\Controllers\Admin\AppearanceController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BlockedPhoneController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SpaController;
use App\Http\Controllers\Admin\UserController;
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

        // Ads
        Route::get('/ads', [AdController::class, 'index'])->middleware('admin.can:ads.view');
        Route::post('/ads/bulk', [AdController::class, 'bulk']); // checks its own permission
        Route::get('/ads/{ad}', [AdController::class, 'show'])->middleware('admin.can:ads.view')->withTrashed();
        Route::post('/ads/{ad}/approve', [AdController::class, 'approve'])->middleware('admin.can:ads.moderate');
        Route::post('/ads/{ad}/reject', [AdController::class, 'reject'])->middleware('admin.can:ads.moderate');
        Route::put('/ads/{ad}', [AdController::class, 'update'])->middleware('admin.can:ads.edit');
        Route::delete('/ads/{ad}', [AdController::class, 'destroy'])->middleware('admin.can:ads.delete');

        // Users (customers) + blocked phone numbers
        Route::get('/users', [UserController::class, 'index'])->middleware('admin.can:users.view');
        Route::get('/users/{user}', [UserController::class, 'show'])->middleware('admin.can:users.view');
        Route::post('/users/{user}/ban', [UserController::class, 'ban'])->middleware('admin.can:users.ban');
        Route::post('/users/{user}/unban', [UserController::class, 'unban'])->middleware('admin.can:users.ban');
        Route::get('/blocked-phones', [BlockedPhoneController::class, 'index'])->middleware('admin.can:users.view');
        Route::post('/blocked-phones', [BlockedPhoneController::class, 'store'])->middleware('admin.can:users.ban');
        Route::delete('/blocked-phones/{blockedPhone}', [BlockedPhoneController::class, 'destroy'])->middleware('admin.can:users.ban');

        // Reports
        Route::get('/reports', [ReportController::class, 'index'])->middleware('admin.can:reports.view');
        Route::post('/reports/{report}/handle', [ReportController::class, 'handle'])->middleware('admin.can:reports.handle');

        Route::get('/settings/appearance', [AppearanceController::class, 'show'])->name('appearance');
        Route::put('/settings/appearance', [AppearanceController::class, 'update'])->middleware('admin.can:settings.manage')->name('appearance.update');
    });

    Route::any('/{any}', fn () => response()->json(['message' => 'Not found.'], 404))->where('any', '.*');
});

Route::get('/admin/{path?}', SpaController::class)->where('path', '.*')->name('admin.app');
