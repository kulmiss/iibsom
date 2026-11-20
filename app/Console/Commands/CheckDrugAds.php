<?php

namespace App\Console\Commands;

use App\Support\Safety\DrugScan;
use Illuminate\Console\Command;

/**
 * One-time (or after the word list changes): live ads whose title or description has a drug word
 * go back to "waiting for approval" with the flag "drugs". Nothing is deleted: staff decide in Ads → Drugs.
 */
class CheckDrugAds extends Command
{
    protected $signature = 'iibsom:drugs:check {--dry-run : only list what would be sent for checking}';

    protected $description = 'Send live ads with drug words back to the moderation queue (flag "drugs")';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $found = DrugScan::run($dry);
        foreach ($found as $ad) {
            $this->line("#{$ad['id']}  “{$ad['title']}”  (word: {$ad['word']})");
        }
        $this->info(($dry ? 'Would send ' : 'Sent ').count($found).' ad(s) back for checking.');

        return self::SUCCESS;
    }
}
