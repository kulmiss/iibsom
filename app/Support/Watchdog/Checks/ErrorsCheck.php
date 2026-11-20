<?php

namespace App\Support\Watchdog\Checks;

use App\Support\Watchdog\ErrorCounter;
use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Watchdog;

/** Many errors in 15 minutes (urgent), and every new kind of error once (warning). */
final class ErrorsCheck implements Check
{
    public function key(): string
    {
        return 'health.errors';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        foreach (ErrorCounter::takeNew() as $kind) {
            $watchdog->event(new Finding("{$this->key()}:new:{$kind['id']}", 'warning', "New kind of error: {$kind['class']}",
                "The site had an error it never had before ({$kind['class']} in {$kind['place']}). The same error is not told again.",
                ['Look for it in storage/logs/laravel.log.', 'If it came after an update, tell your developer.'],
                ['class' => $kind['class'], 'place' => $kind['place']]));
        }

        $count = ErrorCounter::recent(15);
        if ($count < max(1, $limit)) {
            return [];
        }

        return [new Finding($this->key().':spike', 'urgent', "Many errors: {$count} in 15 minutes",
            "The site had {$count} errors in the last 15 minutes (your limit is {$limit}). Visitors may see error pages.",
            ['Open the site on your phone to see what visitors see.', 'Look at storage/logs/laravel.log on the server.', 'If you just updated the site, think about going back.'],
            ['errors' => $count])];
    }
}
