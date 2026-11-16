<?php

return [

    /*
    | Where the site runs: "vps" or "shared".
    | Shared hosting mode: no Redis, no always-running worker. Cron runs the
    | scheduler every minute, and the scheduler also works the queue.
    */
    'hosting' => env('IIBSOM_HOSTING', 'vps') === 'shared' ? 'shared' : 'vps',

    // How long feature switches and settings are cached (seconds).
    'cache_ttl' => (int) env('IIBSOM_CACHE_TTL', 3600),

    // The scheduler writes a heartbeat; older than this = "not running".
    'heartbeat_max_age' => 180,

    // Default values for settings the Super Admin can change.
    'settings' => [
        'ads.expiry_days' => 60,
        'ads.max_per_day' => 5,
        'ads.max_photos' => 8,
        'currency' => 'USD',
        'phone.country_codes' => ['+252'],
    ],

];
