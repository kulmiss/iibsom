<?php

namespace App\Support\Watchdog\Checks;

use App\Models\User;
use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Watchdog;

/** Many new accounts in one hour (robots). Demo accounts are not counted. */
final class SignupsCheck implements Check
{
    public function key(): string
    {
        return 'security.signups';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        $count = User::where('created_at', '>=', now()->subHour())->where('is_demo', false)->count();
        if ($count < max(1, $limit)) {
            return [];
        }

        return [new Finding($this->key(), 'warning', "{$count} new accounts in one hour",
            "{$count} accounts were made in the last hour (your limit is {$limit}). It can be good news, or robots.",
            ['Open Admin → Users, sort by newest, and look at the names and phone numbers.', 'Ban the fake ones (their ads come down too).'],
            ['accounts' => $count])];
    }
}
