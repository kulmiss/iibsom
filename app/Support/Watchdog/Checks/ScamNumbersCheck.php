<?php

namespace App\Support\Watchdog\Checks;

use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Signals;
use App\Support\Watchdog\Watchdog;

/** A phone number on the scam list was used to post an ad (PR 109). It was stopped; staff should know. */
final class ScamNumbersCheck implements Check
{
    public function key(): string
    {
        return 'security.scam_numbers';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        $tries = Signals::recent('scam_post_try', 60);
        if ($tries < max(1, $limit)) {
            return [];
        }

        // No number and no name in the alert (alerts go by email / Telegram): staff look in the security log or Admin → Scam numbers.
        return [new Finding($this->key(), 'warning', "A number on the scam list tried to post ({$tries}×)",
            "In the last hour, someone tried {$tries} times to post or renew an ad with a phone number on the scam list. It was stopped.",
            ['Nothing to do if it keeps being stopped.', 'If the same person keeps trying with new accounts, ban the account in Admin → Users.'],
            ['tries' => $tries])];
    }
}
