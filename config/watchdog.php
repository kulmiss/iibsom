<?php

/*
| Watchdog (PR 94): checks that run from the normal scheduler (one cron line) and tell the owner
| by email and/or Telegram when something is wrong. Nothing here runs inside a visitor's request.
|
| Every check: area (card on the Watchdog page), label + help (easy English, shown in the admin),
| how often, its level, a default limit (the owner can change it) and whether it is ON by default.
*/

return [

    // History: fixed problems older than this are deleted every night.
    'keep_days' => 30,

    // The Watchdog's small memory file (null = storage/app/watchdog/state.json; tests use a temporary file).
    'state_path' => env('WATCHDOG_STATE_PATH'),

    // The time zone of the digest, quiet hours and the times shown in messages.
    'timezone' => 'Africa/Mogadishu',

    // Database connection the health checks and /up test (null = the normal one; tests use a broken one).
    'connection' => env('WATCHDOG_DB_CONNECTION'),

    // Backups are made outside the app (docs/deploy-vps.md). Set the folder to turn the backup check on.
    'backup_path' => env('WATCHDOG_BACKUP_PATH'),
    'backup_pattern' => env('WATCHDOG_BACKUP_PATTERN', 'iibsom-*.sql.gz'),

    // Folders whose files are fingerprinted every night (relative to the project folder).
    'code_folders' => ['app', 'bootstrap/app.php', 'config', 'routes', 'resources/views', 'public/index.php', 'public/.htaccess', 'composer.lock'],

    // Folders that must never hold PHP files (people's uploads).
    'upload_folders' => ['storage/app/public', 'storage/app/private'],

    'checks' => [
        // A. Site health
        'health.database' => ['area' => 'health', 'group' => 'frequent', 'level' => 'urgent', 'on' => true, 'every' => '5 min',
            'label' => 'Database', 'help' => 'The site can reach its database.'],
        'health.cache' => ['area' => 'health', 'group' => 'frequent', 'level' => 'urgent', 'on' => true, 'every' => '5 min',
            'label' => 'Cache', 'help' => 'The cache (Redis or database) works.'],
        'health.speed' => ['area' => 'health', 'group' => 'frequent', 'level' => 'warning', 'on' => true, 'every' => '5 min', 'limit' => 3000, 'unit' => 'ms',
            'label' => 'Page speed', 'help' => 'The home page and the newest ad open. Warning when one is slower than this two checks in a row; urgent when it does not open.'],
        'health.errors' => ['area' => 'health', 'group' => 'frequent', 'level' => 'urgent', 'on' => true, 'every' => '5 min', 'limit' => 20, 'unit' => 'errors in 15 min',
            'label' => 'Errors', 'help' => 'Urgent when the site has this many errors in 15 minutes. A new kind of error is a warning (the same error is told once).'],
        'health.queue' => ['area' => 'health', 'group' => 'frequent', 'level' => 'warning', 'on' => true, 'every' => '5 min', 'limit' => 15, 'unit' => 'minutes',
            'label' => 'Background jobs', 'help' => 'Warning when a job waits longer than this, or when a job failed in the last 24 hours.'],
        'health.scheduler' => ['area' => 'health', 'group' => 'frequent', 'level' => 'warning', 'on' => true, 'every' => '5 min', 'limit' => 15, 'unit' => 'minutes',
            'label' => 'Cron (scheduler)', 'help' => 'Tells you when the cron stopped for longer than this and then came back. When it stops for good, only the outside monitor can tell you (see the guide).'],
        'health.disk' => ['area' => 'health', 'group' => 'frequent', 'level' => 'warning', 'on' => true, 'every' => '5 min', 'limit' => 80, 'unit' => '% full',
            'label' => 'Disk space', 'help' => 'Warning when the disk is this full, urgent at 90%.'],
        'health.uploads' => ['area' => 'health', 'group' => 'daily', 'level' => 'warning', 'on' => true, 'every' => 'daily', 'limit' => 0, 'unit' => 'MB allowed (0 = no limit)',
            'label' => 'Uploads folder', 'help' => 'Size of the uploads folder. On shared hosting put your plan\'s limit here: warning at 80%, urgent at 90%.'],
        'health.backups' => ['area' => 'backups', 'group' => 'daily', 'level' => 'urgent', 'on' => true, 'every' => 'daily (file test weekly)', 'limit' => 26, 'unit' => 'hours',
            'label' => 'Backups', 'help' => 'Last night\'s backup exists (newer than this), its size is normal, and once a week the file opens. Needs WATCHDOG_BACKUP_PATH in .env.'],
        'health.mail' => ['area' => 'health', 'group' => 'daily', 'level' => 'warning', 'on' => true, 'every' => 'daily',
            'label' => 'Email sending', 'help' => 'Sends a short test mail to the alert address every day (only when email is set up).'],
        'health.ssl' => ['area' => 'health', 'group' => 'daily', 'level' => 'warning', 'on' => true, 'every' => 'daily', 'limit' => 14, 'unit' => 'days',
            'label' => 'SSL certificate', 'help' => 'Warning when the https certificate ends in fewer days than this, urgent under 3 days.'],
        'health.app_settings' => ['area' => 'health', 'group' => 'frequent', 'level' => 'urgent', 'on' => true, 'every' => '5 min',
            'label' => 'App settings', 'help' => 'On the live site APP_DEBUG must be false and APP_ENV production.'],
    ],

    // Defaults of the settings on the Watchdog page (stored in the "watchdog" setting).
    'defaults' => [
        'email_on' => true,
        'email_to' => '',
        'telegram_on' => true,
        'telegram_chat_id' => '',
        'quiet_on' => false,
        'quiet_from' => '23:00',
        'quiet_to' => '06:00',
        'digest_time' => '08:00',
        'reminder_hours' => 6,
        'heartbeat_url' => '',
    ],
];
