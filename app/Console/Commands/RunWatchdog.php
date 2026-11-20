<?php

namespace App\Console\Commands;

use App\Support\Watchdog\Runner;
use Illuminate\Console\Command;
use Throwable;

/** Watchdog checks (PR 94). Run by the scheduler: "frequent" every 5 minutes, "daily" once a night. */
class RunWatchdog extends Command
{
    protected $signature = 'iibsom:watchdog {group=frequent : frequent | daily}';

    protected $description = 'Run the Watchdog checks and send alerts';

    public function handle(Runner $runner): int
    {
        try {
            $on = feature('watchdog');
        } catch (Throwable) {
            $on = true; // the switch cannot be read: the database may be down, which is what the Watchdog must tell
        }
        if (! $on) {
            Runner::markRun();
            $this->line('Watchdog switch is OFF.');

            return self::SUCCESS;
        }

        $group = (string) $this->argument('group');
        if (! array_key_exists($group, Runner::GROUPS)) {
            $this->error('Unknown group. Use: '.implode(' | ', array_keys(Runner::GROUPS)));

            return self::INVALID;
        }

        foreach ($runner->run($group) as $check => $result) {
            $this->line(str_pad($check, 26).$result);
        }

        return self::SUCCESS;
    }
}
