<?php

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
};

Route::middleware('locale')->group($publicRoutes);

Route::prefix('{locale}')
    ->where(['locale' => app(Locales::class)->prefixPattern()])
    ->middleware('locale')
    ->name('loc.')
    ->group($publicRoutes);
