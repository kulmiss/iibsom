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

    'ads.edit_changes' => ['group' => 'Ads', 'default' => true, 'label' => 'Show what sellers changed', 'description' => 'An ad edited after approval shows "Edited" in the queue, with the old and new text side by side.'],
    'ads.my_ads' => ['group' => 'Ads', 'default' => true, 'label' => 'My Ads page', 'description' => 'Sellers see their ads and can edit, mark sold, renew and delete them.'],

    // Safety and spam
    'safety.bad_words' => ['group' => 'Safety', 'default' => true, 'label' => 'Bad word filter', 'description' => 'Ads with words from the list in Settings are refused.'],
    'safety.block_links' => ['group' => 'Safety', 'default' => true, 'label' => 'No web links in ads', 'description' => 'Ads with web links (often scams) are refused.'],
    'safety.honeypot' => ['group' => 'Safety', 'default' => true, 'label' => 'Bot trap', 'description' => 'A hidden field catches robots that fill every box.'],

    // Contact buttons
    'contact.whatsapp' => ['group' => 'Contact', 'default' => true, 'label' => 'WhatsApp button', 'description' => 'Big WhatsApp button on the ad page.'],
    'contact.call' => ['group' => 'Contact', 'default' => true, 'label' => 'Call button', 'description' => 'Big Call button on the ad page.'],
    'share.buttons' => ['group' => 'Contact', 'default' => true, 'label' => 'Share buttons', 'description' => 'Share on WhatsApp, Facebook, copy link.'],

    // Site content
    'site.safety_box' => ['group' => 'Site', 'default' => true, 'label' => 'Safety tips box', 'description' => 'Safety tips on every ad page.'],
    'site.banners' => ['group' => 'Site', 'default' => true, 'label' => 'Home banners', 'description' => 'Banners on the home page.'],
    'site.language_switch' => ['group' => 'Site', 'default' => true, 'label' => 'Language switcher', 'description' => 'Visitors can change the language. Hidden while only one language is ON.'],
    'reports.weekly_email' => ['group' => 'Site', 'default' => true, 'label' => 'Weekly summary email', 'description' => 'Every Monday morning the owner gets last week\'s numbers by email (addresses in Settings → General).'],
    'recommendations' => ['group' => 'Site', 'default' => true, 'label' => 'Recommended for you', 'description' => 'Home page shows ads like the ones a visitor looked at, and recently viewed ads. History stays in the visitor\'s browser only.'],
    'site.demo_data' => ['group' => 'Site', 'default' => false, 'label' => 'Show demo data', 'description' => 'Show the demo ads. Never ON in production.'],

    // Speed and API
    'performance.page_cache' => ['group' => 'Speed', 'default' => true, 'label' => 'Page cache', 'description' => 'Pages for visitors who are not signed in are kept for 2 minutes. Changes show at once (the cache is cleared on every change).'],
    'api' => ['group' => 'Speed', 'default' => true, 'label' => 'Public API (/api/v1)', 'description' => 'Read-only JSON for the future mobile app: categories, cities, ads. No personal data.'],

    // Phase 2: money + trust (build only when the owner says)
    'boosts' => ['group' => 'Phase 2', 'default' => false, 'label' => 'Boosts', 'description' => 'Top / VIP / Diamond ads. Paid outside the site; an admin turns them on.'],
    'verified_badge' => ['group' => 'Phase 2', 'default' => false, 'label' => 'Verified badge', 'description' => 'Sellers verified by phone + ID.'],
    'shops' => ['group' => 'Phase 2', 'default' => false, 'label' => 'Business shop pages', 'description' => 'Shop pages with monthly plans, activated by an admin.'],
    'chat' => ['group' => 'Phase 2', 'default' => false, 'label' => 'In-app chat', 'description' => 'Buyers and sellers chat inside the site.'],
    'favourites' => ['group' => 'Phase 2', 'default' => false, 'label' => 'Favourites', 'description' => 'Save ads to a list.'],
    'saved_searches' => ['group' => 'Phase 2', 'default' => false, 'label' => 'Saved search alerts', 'description' => 'Get alerts for new matching ads.'],
    'banner_ads' => ['group' => 'Phase 2', 'default' => false, 'label' => 'Banner ads', 'description' => 'Banner ads for businesses, activated by an admin.'],
    'ad_stats' => ['group' => 'Phase 2', 'default' => false, 'label' => 'Ad stats', 'description' => 'Views, calls and WhatsApp clicks per ad.'],

    // Phase 3: growth
    'app' => ['group' => 'Phase 3', 'default' => false, 'label' => 'Mobile app', 'description' => 'Flutter app + push notifications.'],

];
