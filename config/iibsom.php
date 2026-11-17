<?php

return [

    /*
    | Where the site runs: "vps" or "shared".
    | Shared hosting mode: no Redis, no always-running worker. Cron runs the
    | scheduler every minute, and the scheduler also works the queue.
    */
    'hosting' => env('HOSTING_MODE', env('IIBSOM_HOSTING', 'vps')) === 'shared' ? 'shared' : 'vps',

    // Search: MySQL FULLTEXT for words of 3+ letters (off = LIKE only; tests use LIKE
    // because FULLTEXT can't see rows inside a test transaction).
    'search_fulltext' => (bool) env('SEARCH_FULLTEXT', true),

    // Where finished ad photos go: "public" (your PC) or "r2" (live site).
    'photos_disk' => env('PHOTOS_DISK', 'public'),

    // Photo sizes made in the background (longest side, px).
    'photo_sizes' => ['thumb' => 400, 'medium' => 900, 'large' => 1600],

    // How long feature switches and settings are cached (seconds).
    'cache_ttl' => (int) env('IIBSOM_CACHE_TTL', 3600),

    // The scheduler writes a heartbeat; older than this = "not running".
    'heartbeat_max_age' => 180,

    /*
    | Languages the site knows. The default language has no URL prefix;
    | others use /{code}/... Which ones are ON is a setting (languages.enabled).
    | Add a language: add it here + create lang/{code}/ files.
    */
    'default_locale' => 'en',
    'locales' => [
        'en' => ['name' => 'English', 'native' => 'English', 'dir' => 'ltr'],
        'so' => ['name' => 'Somali', 'native' => 'Soomaali', 'dir' => 'ltr'],
    ],

    // Default values for settings the Super Admin can change.
    'settings' => [
        'languages.enabled' => ['en'],
        'admin.brand_color' => '#1f6fd1',
        'ads.expiry_days' => 60,
        'ads.max_per_day' => 5,
        'ads.max_photos' => 8,
        'currency' => 'USD',
        'phone.country_codes' => ['+252'],
        // One word or phrase per line. Matched as whole words, any case. Admin → Settings → General.
        'safety.bad_words' => ['fuck', 'shit', 'bitch', 'porn', 'escort', 'sex service', 'western union only', 'send money first'],
        'ads.renew_days_before' => 7, // "Renew" shows this many days before an ad ends
        'reports.weekly_emails' => [], // Admin → Settings → General → Weekly summary
        'reports.weekly_last_sent' => '', // e.g. "2026-W46" (set by the command)
        'payments.test_mode' => true, // TEST MODE: nobody should send real money; test payments stay out of the real books
        'payments.numbers' => ['evc_plus' => '', 'zaad' => '', 'sahal' => '', 'edahab' => ''], // merchant numbers buyers pay to
        'setup.completed_at' => '', // first-time setup wizard: '' = not done yet
        'site.support_email' => '', // shown on the Help page ("Still need help?") and as "Contact us" in the footer; empty = hidden
        // Footer (switch "site.new_footer"): social pages (empty = hidden) and which links are ON.
        'footer.social' => ['facebook' => '', 'instagram' => '', 'tiktok' => '', 'youtube' => '', 'telegram' => '', 'whatsapp' => ''],
        'footer.links' => ['about', 'terms', 'privacy', 'cookies', 'copyright', 'contact', 'safety', 'help', 'popular'],
        // Diaspora prices (switch "diaspora"): how much 1 US dollar is in each money. 0 = not shown.
        'diaspora.rates' => ['EUR' => 0, 'GBP' => 0, 'SEK' => 0, 'NOK' => 0, 'CAD' => 0, 'AUD' => 0, 'AED' => 0, 'KES' => 0],
        'diaspora.rates_date' => '', // the day the rates were last changed (shown as "rate of ...")
    ],

    // Money shown to buyers abroad, picked from the phone's time zone (nothing is stored).
    'diaspora_currencies' => [
        'EUR' => 'Euro', 'GBP' => 'British pound', 'SEK' => 'Swedish krona', 'NOK' => 'Norwegian krone',
        'CAD' => 'Canadian dollar', 'AUD' => 'Australian dollar', 'AED' => 'UAE dirham', 'KES' => 'Kenyan shilling',
    ],

];
