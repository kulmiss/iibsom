<?php

namespace App\Support\Watchdog\Checks;

use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Signals;
use App\Support\Watchdog\Watchdog;

/** Banned users signing in, or blocked phone numbers used again, many times in one hour. */
final class BannedCheck implements Check
{
    public function key(): string
    {
        return 'security.banned';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        $tries = Signals::recent('banned_retry', 60);
        if ($tries < max(1, $limit)) {
            return [];
        }

        return [new Finding($this->key(), 'warning', "Banned people tried again {$tries} times",
            "In the last hour, banned users tried to sign in or blocked phone numbers were used {$tries} times. They were stopped.",
            ['Nothing to do if they keep being stopped.', 'If they come back with new accounts, block their phone numbers in Admin → Users.'],
            ['tries' => $tries])];
    }
}
