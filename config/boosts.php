<?php

/*
| Paid boosts (Phase 2, switch "boosts"). Prices are whole US cents.
| These prices are placeholders: the owner sets the real ones (LATER.md).
| level: higher shows first in lists (Diamond > VIP > Top).
*/

return [
    'packages' => [
        'top' => ['level' => 1, 'days' => 7, 'price_cents' => 300],
        'vip' => ['level' => 2, 'days' => 14, 'price_cents' => 800],
        'diamond' => ['level' => 3, 'days' => 30, 'price_cents' => 1500],
    ],

    // Somali mobile money. The merchant number for each is set in admin → Settings → General.
    'methods' => [
        'evc_plus' => 'EVC Plus',
        'zaad' => 'Zaad',
        'sahal' => 'Sahal',
        'edahab' => 'eDahab',
    ],
];
