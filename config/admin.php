<?php

/*
| Staff roles and what each one may do. Super Admin may do everything.
| Every admin API route checks one of these permissions.
*/

return [

    'roles' => [
        'super_admin' => ['label' => 'Super Admin', 'permissions' => ['*']],

        'moderator' => ['label' => 'Moderator', 'permissions' => [
            'dashboard.view',
            'ads.view', 'ads.moderate', 'ads.edit', 'ads.delete',
            'reports.view', 'reports.handle',
            'users.view', 'users.ban',
            'activity.view',
        ]],

        'support' => ['label' => 'Support', 'permissions' => [
            'dashboard.view',
            'ads.view',
            'reports.view',
            'users.view',
        ]],
    ],

    // Every permission with an easy description (shown in the admin "Roles" help).
    'permissions' => [
        'dashboard.view' => 'See the dashboard',
        'ads.view' => 'See all ads',
        'ads.moderate' => 'Approve and reject ads',
        'ads.edit' => 'Edit any ad',
        'ads.delete' => 'Delete ads',
        'reports.view' => 'See reports',
        'reports.handle' => 'Handle reports',
        'users.view' => 'See users',
        'users.ban' => 'Ban users and block phone numbers',
        'staff.manage' => 'Add and change staff accounts',
        'categories.manage' => 'Edit categories and their fields',
        'locations.manage' => 'Edit cities and areas',
        'features.manage' => 'Turn features on and off',
        'settings.manage' => 'Change settings',
        'pages.manage' => 'Edit pages (About, Terms...)',
        'banners.manage' => 'Edit home banners',
        'activity.view' => 'See the activity log',
        'health.view' => 'See system health',
        'demo.manage' => 'Show / delete demo data',
    ],

    // Staff are signed out after this many minutes without activity.
    'idle_timeout_minutes' => 60,

];
