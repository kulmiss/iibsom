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
    'ads.guest_reports' => ['group' => 'Ads', 'default' => true, 'label' => 'Report an ad without signing in', 'description' => 'Visitors who are not signed in can report an ad too (reason, short note, phone or email if they want an answer). Bots are stopped without keeping anyone\'s IP; the same report again counts +1; at most 20 guest reports per ad per day. Needs "Report an ad" ON.'],
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
    'site.new_footer' => ['group' => 'Site', 'default' => true, 'label' => 'Big footer', 'description' => 'The footer with the coast picture, link columns (About, Support, Popular), social pages and languages. Links and social pages are set in Settings → General → Footer. OFF: a short row of links.'],
    'sellers.badges' => ['group' => 'Site', 'default' => true, 'label' => 'Seller badges', 'description' => 'Next to the seller on ad pages: "2 years on iibsom" and, while chat is ON, "Replies fast" (answers buyers within an hour). Worked out from dates only.'],
    'ads.card_badges' => ['group' => 'Site', 'default' => true, 'label' => 'Photo count and "Popular" on ads', 'description' => 'Ad cards show how many photos an ad has, and "Popular" for ads with many views (needs "Ad statistics").'],
    'site.header_icons' => ['group' => 'Site', 'default' => true, 'label' => 'Header icons for signed-in people', 'description' => 'On computers: a "My ads" button, and the number of unread messages on the messages icon (phones: on the bottom bar).'],
    'help.how_to_sell' => ['group' => 'Site', 'default' => true, 'label' => 'How to sell', 'description' => 'A short "How to sell fast" help article (English and Somali), opened from the "How to sell" button on the home page and a link on the posting page. Needs "Help page".'],
    'contact.safety_tip' => ['group' => 'Safety', 'default' => true, 'label' => 'Safety tip at the phone number', 'description' => 'When a buyer taps "Show number", a short tip appears: meet in a public place, never pay before you see the item.'],
    'chat.quick_replies' => ['group' => 'Site', 'default' => true, 'label' => 'Quick chat questions', 'description' => 'Buttons like "Is it still available?" that start a chat with one tap (needs "Chat").'],
    'photos.watermark' => ['group' => 'Site', 'default' => false, 'label' => 'Mark on photos', 'description' => 'New ad photos get a small "iibsom" in the corner of the big and middle sizes (makes copying to other sites less useful). Only photos processed while ON; the small list pictures stay clean.'],
    'site.language_switch' => ['group' => 'Site', 'default' => true, 'label' => 'Language switcher', 'description' => 'Visitors can change the language. Hidden while only one language is ON.'],
    'pwa' => ['group' => 'Site', 'default' => true, 'label' => 'Installable website (app icon)', 'description' => 'People can add iibsom to their home screen like an app; repeat visits load faster and a friendly page shows when offline. OFF removes it from phones on their next visit.'],
    'seo.landing_pages' => ['group' => 'Site', 'default' => true, 'label' => 'Brand pages and links for Google', 'description' => 'Pages like "Toyota Cars in Mogadishu", plus links to popular brands and other cities on category pages.'],
    'reports.weekly_email' => ['group' => 'Site', 'default' => true, 'label' => 'Weekly summary email', 'description' => 'Every Monday morning the owner gets last week\'s numbers by email (addresses in Settings → General).'],
    'recommendations' => ['group' => 'Site', 'default' => true, 'label' => 'Recommended for you', 'description' => 'Home page shows ads like the ones a visitor looked at, and recently viewed ads. History stays in the visitor\'s browser only.'],
    'help.centre' => ['group' => 'Site', 'default' => true, 'label' => 'Help page', 'description' => 'Short how-to articles for customers at /help (post an ad, photos, safety, account), in English and Somali. Linked in the footer and the account page.'],
    'admin.setup_wizard' => ['group' => 'Site', 'default' => true, 'label' => 'First-time setup wizard', 'description' => '5 short steps for the Super Admin at the first sign-in: languages, ad rules, cities, emails, demo data. Can be run again from Settings → General.'],
    'help.admin_guide' => ['group' => 'Site', 'default' => true, 'label' => 'Admin guide', 'description' => 'The "?" button next to each admin page title, the Guide page and its PDF. OFF hides them (for staff who know the admin well).'],
    'site.welcome_tour' => ['group' => 'Site', 'default' => true, 'label' => 'Welcome tour', 'description' => '3 short slides (find, sell, stay safe) shown once after a customer\'s first sign-in, and from Account → Quick tour.'],
    'search.more_filters' => ['group' => 'Site', 'default' => true, 'label' => 'More search filters', 'description' => 'On category and search pages: area, "posted within", only with photos, verified sellers, most viewed, quick price ranges with counts and the live "Show N results" number. OFF keeps only search, price, condition and the category fields.'],
    'catalog.hide_empty' => ['group' => 'Site', 'default' => false, 'label' => 'Hide empty sub categories', 'description' => 'Sub categories with no live ads are not shown on the home page panel and the category pages (sellers can still post in them). Turn ON once the site has many ads.'],
    'site.demo_data' => ['group' => 'Site', 'default' => false, 'label' => 'Show demo data', 'description' => 'Show the demo ads. Never ON in production.'],

    // Speed and API
    'performance.page_cache' => ['group' => 'Speed', 'default' => true, 'label' => 'Page cache', 'description' => 'Pages for visitors who are not signed in are kept for 2 minutes. Changes show at once (the cache is cleared on every change).'],
    'api' => ['group' => 'Speed', 'default' => true, 'label' => 'Public API (/api/v1)', 'description' => 'Read-only JSON for the future mobile app: categories, cities, ads. No personal data.'],
    'api.write' => ['group' => 'Speed', 'default' => false, 'label' => 'Write API (app access tokens)', 'description' => 'Signed-in users make an "app access" key on their Account page; an app can then post, edit, mark sold and delete THEIR OWN ads at /api/v1. Same checks as the website. Turn on when the mobile app is ready.'],

    // Phase 2: money + trust (build only when the owner says)
    'boosts' => ['group' => 'Phase 2', 'default' => false, 'label' => 'Paid boosts', 'description' => 'Sellers pay for Top / VIP / Diamond with EVC Plus, Zaad, Sahal or eDahab and upload the receipt; staff approve it and the ad shows first in lists. Starts in TEST MODE (Settings → General).'],
    'verified_badge' => ['group' => 'Phase 2', 'default' => false, 'label' => 'Verified badge', 'description' => 'Sellers send a photo of their ID; staff check it and give a "Verified" badge shown next to the seller name. The ID photo is kept encrypted and deleted after the check.'],
    'shops' => ['group' => 'Phase 2', 'default' => false, 'label' => 'Business shop pages', 'description' => 'Sellers make a shop page (logo, hours, all their ads) at /shop/name. It is public only while an admin has turned it on for some months (paid outside the site for now).'],
    'chat' => ['group' => 'Phase 2', 'default' => false, 'label' => 'In-website chat', 'description' => 'Buyers tap "Chat with seller" on an ad and talk inside the site (Messages). Works on shared hosting: new messages arrive every few seconds. Either side can block.'],
    'favourites' => ['group' => 'Phase 2', 'default' => false, 'label' => 'Favourites', 'description' => 'Save ads to a list.'],
    'saved_searches' => ['group' => 'Phase 2', 'default' => false, 'label' => 'Saved search alerts', 'description' => 'Get alerts for new matching ads.'],
    'banner_ads' => ['group' => 'Phase 2', 'default' => false, 'label' => 'Banner ads', 'description' => 'Paid banners for businesses on the home page, lists of ads and ad pages, aimed at a category and/or city, with start and end dates and click counts (admin → Home banners).'],
    'ad_stats' => ['group' => 'Phase 2', 'default' => false, 'label' => 'Ad stats', 'description' => 'Sellers see how many people viewed each ad and tapped Show number, WhatsApp or Call (last 30 days). Only numbers are kept: nothing about the visitor.'],

    // Phase 3: growth
    'price_suggestions' => ['group' => 'Phase 3', 'default' => false, 'label' => 'Price guide', 'description' => 'While posting, sellers see "Most Toyota Vitz ads are $4,500 – $7,000" from similar live and sold ads of the last 6 months (only when there are at least 5, so no single price shows).'],
    'seller_ratings' => ['group' => 'Phase 3', 'default' => false, 'label' => 'Seller ratings', 'description' => 'Buyers give a seller 1–5 stars (and a short comment) from the chat, only after the seller answered them. Stars show next to the seller name; staff can hide unfair ratings (admin → Ratings). Needs "In-website chat" ON.'],
    'social.autopost' => ['group' => 'Phase 3', 'default' => false, 'label' => 'Auto-post to Facebook and Telegram', 'description' => 'Each new ad is shared once, when it first goes live, to the iibsom Facebook Page and/or Telegram channel (only the networks whose keys are in .env). Demo ads never. A failed post never stops the ad. See System health.'],
    'diaspora' => ['group' => 'Phase 3', 'default' => false, 'label' => 'Help for buyers abroad (diaspora)', 'description' => 'On ad pages, visitors whose phone is set to a time zone outside Somalia see a safety note for buying for family from abroad, and the price in their money ("≈ €5,980"), from the exchange rates in Settings → General. Nothing about the visitor is stored.'],
    'app' => ['group' => 'Phase 3', 'default' => false, 'label' => 'Mobile app', 'description' => 'Flutter app + push notifications.'],

];
