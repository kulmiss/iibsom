<?php

/*
|--------------------------------------------------------------------------
| Feature switches
|--------------------------------------------------------------------------
|
| Every feature has one switch. The value here is the DEFAULT. The Super
| Admin can change it; that change is saved in the `features` table and
| wins over the default. OFF means hidden everywhere (pages, menus, API,
| sitemap) and its routes answer 404, as if it never existed. Data is kept.
|
| Per-category, per-city and per-language switches live on those records.
|
*/

return [

    // Sign-in
    'login.google' => ['group' => 'Sign-in', 'default' => true, 'label' => 'Continue with Google', 'description' => 'Customers sign in with their Google account.'],
    'login.phone' => ['group' => 'Sign-in', 'default' => false, 'label' => 'Phone + SMS code login', 'description' => 'Customers sign in with a code sent by SMS. Built for later.'],

    // Ads
    'ads.posting' => ['group' => 'Ads', 'default' => true, 'label' => 'Post new ads', 'description' => 'Signed-in users can post ads. OFF = nobody can post.'],
    'ads.approval' => ['group' => 'Ads', 'default' => true, 'label' => 'Admin approval', 'description' => 'New and edited ads wait for an admin before going live.'],
    'ads.trusted_sellers' => ['group' => 'Ads', 'default' => false, 'label' => 'Trusted sellers skip approval', 'description' => 'Sellers with enough approved ads post instantly.'],
    'ads.expiry' => ['group' => 'Ads', 'default' => true, 'label' => 'Ads expire', 'description' => 'Ads end after the number of days in Settings and can be renewed.'],
    'ads.reports' => ['group' => 'Ads', 'default' => true, 'label' => 'Report an ad', 'description' => 'Signed-in users can report bad ads.'],
    'ads.similar' => ['group' => 'Ads', 'default' => true, 'label' => 'Similar ads', 'description' => 'Show similar ads on the ad page.'],

    // Contact buttons
    'contact.whatsapp' => ['group' => 'Contact', 'default' => true, 'label' => 'WhatsApp button', 'description' => 'Big WhatsApp button on the ad page.'],
    'contact.call' => ['group' => 'Contact', 'default' => true, 'label' => 'Call button', 'description' => 'Big Call button on the ad page.'],
    'share.buttons' => ['group' => 'Contact', 'default' => true, 'label' => 'Share buttons', 'description' => 'Share on WhatsApp, Facebook, copy link.'],

    // Site content
    'site.safety_box' => ['group' => 'Site', 'default' => true, 'label' => 'Safety tips box', 'description' => 'Safety tips on every ad page.'],
    'site.banners' => ['group' => 'Site', 'default' => true, 'label' => 'Home banners', 'description' => 'Banners on the home page.'],
    'site.language_switch' => ['group' => 'Site', 'default' => true, 'label' => 'Language switcher', 'description' => 'Visitors can change the language. Hidden while only one language is ON.'],
    'site.demo_data' => ['group' => 'Site', 'default' => false, 'label' => 'Show demo data', 'description' => 'Show the demo ads. Never ON in production.'],

    // Phase 2: money + trust (build only when the owner says)
    'boosts' => ['group' => 'Phase 2', 'default' => false, 'label' => 'Paid boosts', 'description' => 'Top / VIP / Diamond ads.'],
    'payments' => ['group' => 'Phase 2', 'default' => false, 'label' => 'Payments', 'description' => 'EVC Plus, Zaad, Sahal, eDahab.'],
    'verified_badge' => ['group' => 'Phase 2', 'default' => false, 'label' => 'Verified badge', 'description' => 'Sellers verified by phone + ID.'],
    'shops' => ['group' => 'Phase 2', 'default' => false, 'label' => 'Business shop pages', 'description' => 'Shop pages with monthly plans.'],
    'chat' => ['group' => 'Phase 2', 'default' => false, 'label' => 'In-app chat', 'description' => 'Buyers and sellers chat inside the site.'],
    'favourites' => ['group' => 'Phase 2', 'default' => false, 'label' => 'Favourites', 'description' => 'Save ads to a list.'],
    'saved_searches' => ['group' => 'Phase 2', 'default' => false, 'label' => 'Saved search alerts', 'description' => 'Get alerts for new matching ads.'],
    'banner_ads' => ['group' => 'Phase 2', 'default' => false, 'label' => 'Banner ads', 'description' => 'Paid banner ads for businesses.'],
    'ad_stats' => ['group' => 'Phase 2', 'default' => false, 'label' => 'Ad stats', 'description' => 'Views, calls and WhatsApp clicks per ad.'],

    // Phase 3: growth
    'app' => ['group' => 'Phase 3', 'default' => false, 'label' => 'Mobile app', 'description' => 'Flutter app + push notifications.'],

];
