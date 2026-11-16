<?php

return [

    /*
    | Where the site runs: "vps" or "shared".
    | Shared hosting mode: no Redis, no always-running worker. Cron runs the
    | scheduler every minute, and the scheduler also works the queue.
    */
    'hosting' => env('HOSTING_MODE', env('IIBSOM_HOSTING', 'vps')) === 'shared' ? 'shared' : 'vps',

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
        'ads.expiry_days' => 60,
        'ads.max_per_day' => 5,
        'ads.max_photos' => 8,
        'currency' => 'USD',
        'phone.country_codes' => ['+252'],
    ],

];
