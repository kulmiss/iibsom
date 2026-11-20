<?php

namespace App\Support\Watchdog\Checks;

use App\Support\Safety\DrugScan;
use App\Support\Watchdog\Finding;
use App\Support\Watchdog\State;
use App\Support\Watchdog\Watchdog;
use Illuminate\Support\Carbon;

/**
 * Runs the drug word check (the same one as `iibsom:drugs:check`) on live ads changed since the last look.
 * Posting already refuses drug words; this catches what got through (an edit by staff, a word added to the
 * list later). Found ads go back to "Waiting for approval" with the "Drugs" mark; the owner is told once.
 */
final class DrugsCheck implements Check
{
    public function key(): string
    {
        return 'ads.drugs';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        if (! feature('safety.no_drugs')) {
            return [];
        }
        $since = State::get('drugs_checked_at');
        $start = now();
        $found = DrugScan::run(changedSince: is_int($since) ? Carbon::createFromTimestamp($since)->subMinute() : null);
        State::put('drugs_checked_at', $start->getTimestamp());

        if ($found !== []) {
            $count = count($found);
            $first = $found[0];
            $watchdog->event(new Finding("{$this->key()}:{$first['id']}", 'warning', "{$count} live ad(s) had drug words",
                "{$count} live ad(s) had a drug word (first: ad #{$first['id']}, word \"{$first['word']}\"). They were sent back to Waiting for approval with the Drugs mark.",
                ['Open Admin → Ads → filter "Drugs" and decide.', 'Ban the seller if it is clearly selling drugs.'],
                ['ads' => $count], 'ad', $first['id']));
        }

        return [];
    }
}
