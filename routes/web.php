<?php

use App\Http\Controllers\Account\ApiTokenController;
use App\Http\Controllers\Account\BoostController;
use App\Http\Controllers\Account\CallBackController;
use App\Http\Controllers\Account\ChatController;
use App\Http\Controllers\Account\FavouriteController;
use App\Http\Controllers\Account\MyAdsController;
use App\Http\Controllers\Account\NotificationController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\ReviewController;
use App\Http\Controllers\Account\SavedSearchController;
use App\Http\Controllers\Account\ShopController;
use App\Http\Controllers\Account\VerificationController;
use App\Http\Controllers\AdPageController;
use App\Http\Controllers\Ads\AdPhotoController;
use App\Http\Controllers\Ads\PostAdController;
use App\Http\Controllers\Ads\PriceHintController;
use App\Http\Controllers\AdStatsController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TestSignInController;
use App\Http\Controllers\BannerClickController;
use App\Http\Controllers\BrowseController;
use App\Http\Controllers\ForYouController;
use App\Http\Controllers\GuestReportController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SearchSuggestController;
use App\Http\Controllers\SellerReviewsController;
use App\Http\Controllers\ShopPageController;
use App\Http\Controllers\SitemapController;
use App\Models\Page;
use App\Support\Localization\Locales;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
| Public pages are registered once per URL style:
|   other languages:   /so/...     (name: loc.home, parameter locale=so)   ← registered first
|   default language:  /           (name: home)
| Use lroute('home') in views to get the link in the current language.
*/
// For JSON calls from cached guest pages: no session, no cookies, no CSRF token,
// so nothing is saved about the visitor (ad contact + ad stats beacon).
$stateless = [EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class];

$publicRoutes = function () use ($stateless) {
    Route::view('/', 'home')->name('home');

    // Sign in (Google only for customers).
    Route::middleware(['guest', 'feature:login.google'])->group(function () {
        Route::get('/login', [LoginController::class, 'show'])->name('login');
        // Test sign-in: the owner's own computer only (APP_ENV=local); 404 everywhere else.
        Route::post('/login/test', [TestSignInController::class, 'store'])->middleware('throttle:10,1')->name('login.test');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [LoginController::class, 'logout'])->middleware('throttle:30,1')->name('logout');

        Route::get('/welcome', [ProfileController::class, 'welcome'])->name('welcome');
        Route::post('/welcome', [ProfileController::class, 'save'])->middleware('throttle:20,1')->name('welcome.save');

        Route::middleware('profile.complete')->group(function () {
            // Post an ad (signed in + profile complete + switch ON).
            Route::middleware('feature:ads.posting')->group(function () {
                Route::get('/post', [PostAdController::class, 'chooseCategory'])->name('post');
                Route::get('/post/done/{ad}', [PostAdController::class, 'done'])->whereNumber('ad')->name('post.done');
                Route::get('/post/{category:slug}', [PostAdController::class, 'create'])->name('post.form');
                Route::post('/post/{category:slug}', [PostAdController::class, 'store'])->middleware('throttle:10,1')->name('post.store');

                Route::get('/post/{category:slug}/price-hint', [PriceHintController::class, 'show'])->middleware(['feature:price_suggestions', 'throttle:60,1'])->name('post.price_hint');
                Route::post('/post-photos', [AdPhotoController::class, 'store'])->middleware('throttle:60,1')->name('post.photos.store');
                Route::delete('/post-photos/{photo}', [AdPhotoController::class, 'destroy'])->whereNumber('photo')->middleware('throttle:60,1')->name('post.photos.destroy');
            });

            Route::post('/ads/{ad}/report', [AdPageController::class, 'report'])->whereNumber('ad')
                ->middleware(['feature:ads.reports', 'throttle:10,1'])->name('ad.report');

            // My Ads: edit, mark sold, renew, delete (own ads only).
            Route::middleware('feature:ads.my_ads')->prefix('/account/ads')->name('account.ads')->group(function () {
                Route::get('/', [MyAdsController::class, 'index']);
                Route::get('/{ad}/edit', [MyAdsController::class, 'edit'])->whereNumber('ad')->name('.edit');
                Route::put('/{ad}', [MyAdsController::class, 'update'])->whereNumber('ad')->middleware('throttle:20,1')->name('.update');
                Route::post('/{ad}/sold', [MyAdsController::class, 'sold'])->whereNumber('ad')->middleware('throttle:30,1')->name('.sold');
                Route::post('/{ad}/renew', [MyAdsController::class, 'renew'])->whereNumber('ad')->middleware('throttle:30,1')->name('.renew');
                Route::delete('/{ad}', [MyAdsController::class, 'destroy'])->whereNumber('ad')->middleware('throttle:30,1')->name('.destroy');
            });

            Route::get('/account/ads/{ad}/stats', [AdStatsController::class, 'show'])->whereNumber('ad')->middleware('feature:ad_stats')->name('account.ads.stats');

            // In-website chat (Phase 2).
            Route::middleware('feature:chat')->group(function () {
                Route::get('/messages', [ChatController::class, 'index'])->name('chat');
                Route::post('/ads/{ad}/chat', [ChatController::class, 'start'])->whereNumber('ad')->middleware('throttle:20,1')->name('chat.start');
                Route::get('/messages/{conversation}', [ChatController::class, 'show'])->whereNumber('conversation')->name('chat.show');
                Route::post('/messages/{conversation}', [ChatController::class, 'send'])->whereNumber('conversation')->middleware('throttle:20,1')->name('chat.send');
                Route::get('/messages/{conversation}/since', [ChatController::class, 'since'])->whereNumber('conversation')->middleware('throttle:30,1')->name('chat.since');
                Route::post('/messages/{conversation}/block', [ChatController::class, 'block'])->whereNumber('conversation')->middleware('throttle:10,1')->name('chat.block');
                Route::post('/messages/{conversation}/review', [ReviewController::class, 'store'])->whereNumber('conversation')->middleware(['feature:seller_ratings', 'throttle:10,1'])->name('chat.review');
            });

            // Paid boosts (Phase 2): pay by mobile money + receipt; staff approve.
            Route::middleware('feature:boosts')->group(function () {
                Route::get('/account/ads/{ad}/boost', [BoostController::class, 'show'])->whereNumber('ad')->name('account.boost');
                Route::post('/account/ads/{ad}/boost', [BoostController::class, 'store'])->whereNumber('ad')->middleware('throttle:10,60')->name('account.boost.store');
                Route::get('/account/payments', [BoostController::class, 'payments'])->name('account.payments');
            });

            // Business shop page (Phase 2).
            Route::middleware('feature:shops')->group(function () {
                Route::get('/account/shop', [ShopController::class, 'edit'])->name('account.shop');
                Route::post('/account/shop', [ShopController::class, 'save'])->middleware('throttle:20,1')->name('account.shop.save');
            });

            // App access keys for the write API (docs/api.md).
            Route::middleware('feature:api.write')->group(function () {
                Route::get('/account/api', [ApiTokenController::class, 'index'])->name('account.api');
                Route::post('/account/api', [ApiTokenController::class, 'store'])->middleware('throttle:10,60')->name('account.api.store');
                Route::delete('/account/api/{token}', [ApiTokenController::class, 'destroy'])->whereNumber('token')->middleware('throttle:20,1')->name('account.api.destroy');
            });

            // Verified badge (Phase 2): send an ID photo.
            Route::middleware('feature:verified_badge')->group(function () {
                Route::get('/account/verify', [VerificationController::class, 'show'])->name('verify');
                Route::post('/account/verify', [VerificationController::class, 'store'])->middleware('throttle:5,60')->name('verify.store');
            });

            // Notifications bell (PR 66) and "Ask the seller to call me".
            Route::middleware('feature:notifications')->group(function () {
                Route::get('/account/notifications', [NotificationController::class, 'index'])->name('notifications');
                Route::get('/account/notifications/settings', [NotificationController::class, 'settings'])->name('notifications.settings');
                Route::post('/account/notifications/settings', [NotificationController::class, 'save'])->middleware('throttle:20,1')->name('notifications.settings.save');
                Route::post('/ads/{ad}/call-back', [CallBackController::class, 'store'])->whereNumber('ad')->middleware(['feature:contact.call_back', 'throttle:5,1'])->name('call_back');
            });

            // Favourites (Phase 2): saved ads.
            Route::middleware('feature:favourites')->group(function () {
                Route::get('/account/saved', [FavouriteController::class, 'index'])->name('saved');
                Route::post('/favourites/{ad}', [FavouriteController::class, 'toggle'])->whereNumber('ad')->middleware('throttle:60,1')->name('favourite.toggle');
            });

            // Saved searches + daily alerts (Phase 2).
            Route::middleware('feature:saved_searches')->group(function () {
                Route::get('/account/searches', [SavedSearchController::class, 'index'])->name('searches');
                Route::post('/account/searches', [SavedSearchController::class, 'store'])->middleware('throttle:20,1')->name('searches.store');
                Route::delete('/account/searches/{search}', [SavedSearchController::class, 'destroy'])->whereNumber('search')->middleware('throttle:30,1')->name('searches.destroy');
            });

            Route::view('/account', 'account.index')->name('account');
            Route::get('/account/profile', [ProfileController::class, 'edit'])->name('account.profile');
            Route::post('/account/profile', [ProfileController::class, 'save'])->middleware('throttle:20,1')->name('account.profile.save');
        });
    });

    // Report an ad without signing in (no session, no cookie, no IP stored; see GuestReports).
    Route::post('/ads/{ad}/report-guest', [GuestReportController::class, 'store'])->whereNumber('ad')
        ->middleware(['feature:ads.reports', 'feature:ads.guest_reports', 'throttle:5,10'])
        ->withoutMiddleware($stateless)->name('ad.report_guest');
    // A fresh time stamp for that form when it opens (the page itself may come from the page cache).
    Route::get('/report-token', [GuestReportController::class, 'token'])
        ->middleware(['feature:ads.reports', 'feature:ads.guest_reports', 'throttle:30,1'])
        ->withoutMiddleware($stateless)->name('ad.report_token');

    // Seller's number after a tap (JSON, rate-limited).
    Route::get('/ads/{ad}/contact', [AdPageController::class, 'contact'])->whereNumber('ad')->middleware('throttle:30,1')
        ->withoutMiddleware($stateless)->name('ad.contact');

    Route::get('/search', [BrowseController::class, 'search'])->name('search');
    // Suggestions while typing (PR 63): stateless JSON, cached.
    Route::get('/search-suggest', SearchSuggestController::class)->middleware(['feature:search.suggestions', 'throttle:60,1'])
        ->withoutMiddleware($stateless)->name('search.suggest');
    // "Show 120 results" on the phone filter sheet (JSON; no session or cookie for guests).
    Route::get('/browse-count', [BrowseController::class, 'count'])->middleware(['feature:search.more_filters', 'throttle:120,1'])
        ->withoutMiddleware($stateless)->name('browse.count');

    // "Recommended for you" cards (the browser sends its own history; nothing stored).
    Route::get('/for-you', [ForYouController::class, 'show'])->middleware(['feature:recommendations', 'throttle:60,1'])->name('for-you');

    // Business shop pages (Phase 2): public only while an admin has the shop active.
    Route::get('/shop/{shop}', [ShopPageController::class, 'show'])->where('shop', '[a-z0-9]+(-[a-z0-9]+)*')->middleware('feature:shops')->name('shop.show');

    // Seller ratings (Phase 3): public list of a seller's stars.
    Route::get('/seller/{user}/reviews', [SellerReviewsController::class, 'show'])->whereNumber('user')->middleware('feature:seller_ratings')->name('seller.reviews');

    // Help centre for customers (articles in resources/help).
    Route::middleware('feature:help.centre')->group(function () {
        Route::get('/help', [HelpController::class, 'index'])->name('help');
        Route::get('/help/{article}', [HelpController::class, 'show'])->where('article', '[a-z0-9-]+')->name('help.show');
    });

    // About, Terms, Privacy, Safety tips (text edited in the admin).
    Route::get('/{slug}', [PageController::class, 'show'])->whereIn('slug', Page::CORE)->name('page');

    // Category pages and ad pages: /cars, /cars/mogadishu, /cars/mogadishu/toyota-hilux-2016-123 (last, catch-all).
    Route::get('/{category}/{city}/{slugId}', [AdPageController::class, 'show'])
        ->where(['category' => '[a-z0-9-]+', 'city' => '[a-z0-9-]+', 'slugId' => '[a-z0-9-]*-[0-9]+'])->name('ad.show');
    // Brand landing pages: /cars/mogadishu/toyota (brand words never contain digits, ad addresses always end in digits).
    Route::get('/{category}/{city}/{brand}', [BrowseController::class, 'brand'])
        ->where(['category' => '[a-z0-9-]+', 'city' => '[a-z0-9-]+', 'brand' => '[a-z]+(-[a-z]+)*'])
        ->middleware('feature:seo.landing_pages')->name('browse.brand');
    Route::get('/{category}/{city?}', [BrowseController::class, 'category'])
        ->where(['category' => '[a-z0-9-]+', 'city' => '[a-z0-9-]+'])->name('browse.category');
};

// Banner click: counted, then sent on to the banner's link (stateless).
Route::get('/b/{banner}', BannerClickController::class)->whereNumber('banner')->middleware('throttle:60,1')
    ->withoutMiddleware($stateless)->name('banner.click');

// Ad stats beacon (switch "ad_stats"): one number per ad per day, nothing about the visitor.
Route::post('/ads/{ad}/event', [AdStatsController::class, 'event'])
    ->whereNumber('ad')->middleware(['feature:ad_stats', 'throttle:60,1'])
    ->withoutMiddleware($stateless)->name('ad.event');

Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// Google OAuth: fixed URLs (the callback must match Google Cloud settings exactly).
Route::middleware(['feature:login.google', 'throttle:20,1'])->group(function () {
    Route::get('/auth/google', [GoogleController::class, 'redirect'])->middleware('guest')->name('auth.google');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
});

Route::prefix('{locale}')
    ->where(['locale' => app(Locales::class)->prefixPattern()])
    ->middleware('locale')
    ->name('loc.')
    ->group($publicRoutes);

Route::middleware('locale')->group($publicRoutes);
