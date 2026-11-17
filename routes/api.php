<?php

use App\Http\Controllers\Api\V1\AdApiController;
use App\Http\Controllers\Api\V1\CatalogApiController;
use App\Http\Controllers\Api\V1\MyAdsApiController;
use Illuminate\Support\Facades\Route;

/*
| API for the future mobile app: /api/v1/...
| Read part: no cookies, no sign-in, no personal data (phone numbers stay on the website,
| behind "Show number"). Switch: "api". Language: ?lang=so
*/
Route::middleware(['feature:api', 'throttle:60,1', 'api.locale'])->name('api.v1.')->group(function () {
    Route::get('/categories', [CatalogApiController::class, 'categories'])->name('categories');
    Route::get('/categories/{slug}/fields', [CatalogApiController::class, 'fields'])->where('slug', '[a-z0-9-]+')->name('categories.fields');
    Route::get('/cities', [CatalogApiController::class, 'cities'])->name('cities');
    Route::get('/ads', [AdApiController::class, 'index'])->name('ads.index');
    Route::get('/ads/{id}', [AdApiController::class, 'show'])->whereNumber('id')->name('ads.show');

    // Write API (switch "api.write", OFF by default): your own ads, with a token from
    // the website (Account → App access). Same checks as the website. See docs/api.md.
    Route::middleware(['feature:api.write', 'throttle:30,1,api-write'])->group(function () {
        Route::middleware('api.token:read')->group(function () {
            Route::get('/me', [MyAdsApiController::class, 'me'])->name('me');
            Route::get('/me/ads', [MyAdsApiController::class, 'index'])->name('me.ads');
            Route::get('/photos/{id}', [MyAdsApiController::class, 'photoStatus'])->whereNumber('id')->name('photos.show');
        });
        Route::middleware('api.token:write')->group(function () {
            Route::post('/ads', [MyAdsApiController::class, 'store'])->middleware('throttle:10,1,api-post')->name('ads.store');
            Route::put('/ads/{id}', [MyAdsApiController::class, 'update'])->whereNumber('id')->name('ads.update');
            Route::post('/ads/{id}/sold', [MyAdsApiController::class, 'sold'])->whereNumber('id')->name('ads.sold');
            Route::delete('/ads/{id}', [MyAdsApiController::class, 'destroy'])->whereNumber('id')->name('ads.destroy');
            Route::post('/photos', [MyAdsApiController::class, 'photo'])->name('photos.store');
        });
    });
});
