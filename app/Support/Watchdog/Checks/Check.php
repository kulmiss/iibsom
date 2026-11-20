<?php

namespace App\Support\Watchdog\Checks;

use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Watchdog;

/** One Watchdog check. It returns what is wrong now; what is not returned any more counts as fixed. */
interface Check
{
    /** The key in config/watchdog.php → checks. */
    public function key(): string;

    /**
     * @param  int  $limit  The owner's limit for this check (0 when it has none).
     * @return list<Finding>
     */
    public function run(Watchdog $watchdog, int $limit): array;
}
