<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\AdController;
use App\Http\Controllers\Admin\AppearanceController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BlockedPhoneController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FeatureController;
use App\Http\Controllers\Admin\GuideController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\PageAdminController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SpaController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
| Super Admin. JSON API under /admin/api, the React app for every other /admin/* URL.
| Loaded with the "web" middleware (session + CSRF) in bootstrap/app.php.
*/

Route::prefix('admin/api')->group(function () {
    // Sign-in steps (not signed in yet).
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/login', [AuthController::class, 'login'])->name('admin.api.login');
        Route::get('/2fa/setup', [AuthController::class, 'setup'])->name('admin.api.2fa.setup');
        Route::post('/2fa/confirm', [AuthController::class, 'confirm'])->name('admin.api.2fa.confirm');
        Route::post('/2fa/verify', [AuthController::class, 'verify'])->name('admin.api.2fa.verify');
    });

    Route::get('/session', [AuthController::class, 'session'])->middleware('throttle:60,1')->name('admin.api.session');

    Route::middleware('admin')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('admin.api.me');

        // Super Admin guide ("?" buttons, Guide page, print and PDF): every staff member.
        Route::middleware('feature:help.admin_guide')->group(function () {
            Route::get('/guide', [GuideController::class, 'index'])->name('admin.api.guide');
            Route::get('/guide/print', [GuideController::class, 'print'])->name('admin.api.guide.print');
            Route::get('/guide/pdf', [GuideController::class, 'pdf'])->name('admin.api.guide.pdf');
        });
        Route::post('/logout', [AuthController::class, 'logout'])->name('admin.api.logout');

        Route::get('/dashboard', DashboardController::class)->middleware('admin.can:dashboard.view')->name('admin.api.dashboard');
        Route::get('/activity', ActivityController::class)->middleware('admin.can:activity.view')->name('admin.api.activity');

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

        // Categories + custom fields
        Route::middleware('admin.can:categories.manage')->group(function () {
            Route::get('/categories', [CategoryController::class, 'index']);
            Route::post('/categories', [CategoryController::class, 'store']);
            Route::post('/categories/reorder', [CategoryController::class, 'reorder']);
            Route::put('/categories/{category}', [CategoryController::class, 'update']);
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
            Route::get('/categories/{category}/fields', [CategoryController::class, 'fields']);
            Route::post('/categories/{category}/fields', [CategoryController::class, 'storeField']);
            Route::put('/categories/{category}/fields/{field}', [CategoryController::class, 'updateField']);
            Route::delete('/categories/{category}/fields/{field}', [CategoryController::class, 'destroyField']);
        });

        // Regions, cities, areas
        Route::middleware('admin.can:locations.manage')->group(function () {
            Route::get('/locations', [LocationController::class, 'index']);
            Route::put('/regions/{region}', [LocationController::class, 'updateRegion']);
            Route::post('/cities', [LocationController::class, 'storeCity']);
            Route::put('/cities/{city}', [LocationController::class, 'updateCity']);
            Route::get('/cities/{city}/areas', [LocationController::class, 'areas']);
            Route::post('/cities/{city}/areas', [LocationController::class, 'storeArea']);
            Route::put('/areas/{area}', [LocationController::class, 'updateArea']);
        });

        // Feature switches
        Route::middleware('admin.can:features.manage')->group(function () {
            Route::get('/features', [FeatureController::class, 'index']);
            Route::put('/features/{key}', [FeatureController::class, 'update'])->where('key', '[a-z0-9._]+');
            Route::delete('/features/{key}', [FeatureController::class, 'reset'])->where('key', '[a-z0-9._]+');
        });

        // Settings → General
        Route::get('/settings/general', [SettingsController::class, 'show'])->middleware('admin.can:settings.manage');
        Route::put('/settings/general', [SettingsController::class, 'update'])->middleware('admin.can:settings.manage');
        Route::post('/settings/weekly-summary/test', [SettingsController::class, 'testWeeklySummary'])
            ->middleware(['admin.can:settings.manage', 'throttle:5,10'])->name('admin.api.settings.weekly-test');

        // Pages + banners
        Route::middleware('admin.can:pages.manage')->group(function () {
            Route::get('/pages', [PageAdminController::class, 'index'])->name('admin.api.pages');
            Route::put('/pages/{page}', [PageAdminController::class, 'update'])->name('admin.api.pages.update');
            Route::post('/pages/preview', [PageAdminController::class, 'preview'])->name('admin.api.pages.preview');
        });
        Route::middleware('admin.can:banners.manage')->group(function () {
            Route::get('/banners', [BannerController::class, 'index'])->name('admin.api.banners');
            Route::post('/banners', [BannerController::class, 'store'])->name('admin.api.banners.store');
            Route::post('/banners/{banner}', [BannerController::class, 'update'])->name('admin.api.banners.update');
            Route::delete('/banners/{banner}', [BannerController::class, 'destroy'])->name('admin.api.banners.destroy');
        });

        // Staff
        Route::middleware('admin.can:staff.manage')->group(function () {
            Route::get('/staff', [StaffController::class, 'index'])->name('admin.api.staff');
            Route::post('/staff', [StaffController::class, 'store'])->name('admin.api.staff.store');
            Route::put('/staff/{user}', [StaffController::class, 'update'])->name('admin.api.staff.update');
            Route::post('/staff/{user}/reset-2fa', [StaffController::class, 'resetTwoFactor'])->name('admin.api.staff.reset2fa');
            Route::post('/staff/{user}/reset-password', [StaffController::class, 'resetPassword'])->name('admin.api.staff.resetPassword');
            Route::delete('/staff/{user}', [StaffController::class, 'destroy'])->name('admin.api.staff.destroy');
        });

        // System health + demo data
        Route::get('/health', [SystemController::class, 'health'])->middleware('admin.can:health.view')->name('admin.api.health');
        Route::get('/demo', [SystemController::class, 'demo'])->middleware('admin.can:demo.manage')->name('admin.api.demo');
        Route::post('/demo/delete', [SystemController::class, 'deleteDemo'])->middleware(['admin.can:demo.manage', 'throttle:3,1'])->name('admin.api.demo.delete');

        Route::get('/settings/appearance', [AppearanceController::class, 'show'])->name('admin.api.appearance');
        Route::put('/settings/appearance', [AppearanceController::class, 'update'])->middleware('admin.can:settings.manage')->name('admin.api.appearance.update');
    });

    Route::any('/{any}', fn () => response()->json(['message' => 'Not found.'], 404))->where('any', '.*');
});

Route::get('/admin/{path?}', SpaController::class)->where('path', '.*')->name('admin.app');
