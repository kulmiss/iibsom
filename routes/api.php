<?php

use App\Http\Controllers\Api\V1\AdApiController;
use App\Http\Controllers\Api\V1\CatalogApiController;
use Illuminate\Support\Facades\Route;

/*
| Public, read-only API for the future mobile app: /api/v1/...
| No cookies, no sign-in, no personal data (phone numbers stay on the website,
| behind "Show number"). Switch: "api". Language: ?lang=so
*/
Route::middleware(['feature:api', 'throttle:60,1', 'api.locale'])->name('api.v1.')->group(function () {
    Route::get('/categories', [CatalogApiController::class, 'categories'])->name('categories');
    Route::get('/cities', [CatalogApiController::class, 'cities'])->name('cities');
    Route::get('/ads', [AdApiController::class, 'index'])->name('ads.index');
    Route::get('/ads/{id}', [AdApiController::class, 'show'])->whereNumber('id')->name('ads.show');
});
