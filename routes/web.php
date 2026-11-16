<?php

use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Support\Localization\Locales;
use Illuminate\Support\Facades\Route;

/*
| Public pages are registered once per URL style:
|   default language:  /           (name: home)
|   other languages:   /so/        (name: loc.home, parameter locale=so)
| Use lroute('home') in views to get the link in the current language.
*/
$publicRoutes = function () {
    Route::view('/', 'home')->name('home');

    // Sign in (Google only for customers).
    Route::middleware(['guest', 'feature:login.google'])->group(function () {
        Route::get('/login', [LoginController::class, 'show'])->name('login');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

        Route::get('/welcome', [ProfileController::class, 'welcome'])->name('welcome');
        Route::post('/welcome', [ProfileController::class, 'save'])->middleware('throttle:20,1')->name('welcome.save');

        Route::middleware('profile.complete')->group(function () {
            Route::view('/account', 'account.index')->name('account');
            Route::get('/account/profile', [ProfileController::class, 'edit'])->name('account.profile');
            Route::post('/account/profile', [ProfileController::class, 'save'])->middleware('throttle:20,1')->name('account.profile.save');
        });
    });
};

Route::middleware('locale')->group($publicRoutes);

Route::prefix('{locale}')
    ->where(['locale' => app(Locales::class)->prefixPattern()])
    ->middleware('locale')
    ->name('loc.')
    ->group($publicRoutes);

// Google OAuth: fixed URLs (the callback must match Google Cloud settings exactly).
Route::middleware(['feature:login.google', 'throttle:20,1'])->group(function () {
    Route::get('/auth/google', [GoogleController::class, 'redirect'])->middleware('guest')->name('auth.google');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
});
