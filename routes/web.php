<?php

use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Ads\AdPhotoController;
use App\Http\Controllers\Ads\PostAdController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PageController;
use App\Models\Page;
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

    // About, Terms, Privacy, Safety tips (text edited in the admin).
    Route::get('/{slug}', [PageController::class, 'show'])
        ->whereIn('slug', Page::CORE)->name('page');

    // Sign in (Google only for customers).
    Route::middleware(['guest', 'feature:login.google'])->group(function () {
        Route::get('/login', [LoginController::class, 'show'])->name('login');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

        Route::get('/welcome', [ProfileController::class, 'welcome'])->name('welcome');
        Route::post('/welcome', [ProfileController::class, 'save'])->middleware('throttle:20,1')->name('welcome.save');

        Route::middleware('profile.complete')->group(function () {
            // Post an ad (signed in + profile complete + switch ON).
            Route::middleware('feature:ads.posting')->group(function () {
                Route::get('/post', [PostAdController::class, 'chooseCategory'])->name('post');
                Route::get('/post/done/{ad}', [PostAdController::class, 'done'])->whereNumber('ad')->name('post.done');
                Route::get('/post/{category:slug}', [PostAdController::class, 'create'])->name('post.form');
                Route::post('/post/{category:slug}', [PostAdController::class, 'store'])->middleware('throttle:10,1')->name('post.store');

                Route::post('/post-photos', [AdPhotoController::class, 'store'])->middleware('throttle:60,1')->name('post.photos.store');
                Route::delete('/post-photos/{photo}', [AdPhotoController::class, 'destroy'])->whereNumber('photo')->name('post.photos.destroy');
            });

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
