<?php

namespace App\Console\Commands;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Support\Admin\Activity;
use App\Support\Ads\AdCounters;
use App\Support\Cache\PageCache;
use App\Support\Safety\SpamGuard;
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
        $found = 0;
        $dry = (bool) $this->option('dry-run');

        Ad::query()->where('status', AdStatus::Live)->select(['id', 'title', 'description', 'status'])
            ->chunkById(1000, function ($ads) use (&$found, $dry) {
                foreach ($ads as $ad) {
                    $word = SpamGuard::drugWord($ad->title.' '.$ad->description);
                    if ($word === null) {
                        continue;
                    }
                    $found++;
                    $this->line("#{$ad->id}  “{$ad->title}”  (word: {$word})");
                    if (! $dry) {
                        // Only if it is still live (a seller or staff may have changed it meanwhile).
                        Ad::whereKey($ad->id)->where('status', AdStatus::Live)
                            ->update(['status' => AdStatus::Pending->value, 'flag' => 'drugs', 'flag_word' => mb_substr($word, 0, 60), 'updated_at' => now()]);
                    }
                }
            });

        if (! $dry && $found > 0) {
            AdCounters::refreshAll(); // the update above skips the model events that keep the totals
            PageCache::bump();
            Activity::log('ads.drugs_check', "Sent {$found} live ad(s) with drug words back for checking");
        }
        $this->info(($dry ? 'Would send ' : 'Sent ')."{$found} ad(s) back for checking.");

        return self::SUCCESS;
    }
}
