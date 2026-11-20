<?php

namespace App\Support\Safety;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Support\Admin\Activity;
use App\Support\Ads\AdCounters;
use App\Support\Cache\PageCache;
use Illuminate\Support\Carbon;

/**
 * Live ads whose title or description has a drug word go back to "waiting for approval" with the flag "drugs".
 * Nothing is deleted: staff decide in Ads → Drugs. Used by `iibsom:drugs:check` (all live ads) and by the
 * Watchdog (PR 95: only ads changed since its last look).
 */
final class DrugScan
{
    /** @return list<array{id: int, title: string, word: string}> */
    public static function run(bool $dry = false, ?Carbon $changedSince = null): array
    {
        $found = [];
        Ad::query()->where('status', AdStatus::Live)
            ->when($changedSince, fn ($q) => $q->where('updated_at', '>=', $changedSince))
            ->select(['id', 'title', 'description', 'status'])
            ->chunkById(1000, function ($ads) use (&$found, $dry) {
                foreach ($ads as $ad) {
                    $word = SpamGuard::drugWord($ad->title.' '.$ad->description);
                    if ($word === null) {
                        continue;
                    }
                    $found[] = ['id' => (int) $ad->id, 'title' => (string) $ad->title, 'word' => $word];
                    if (! $dry) {
                        // Only if it is still live (a seller or staff may have changed it meanwhile).
                        Ad::whereKey($ad->id)->where('status', AdStatus::Live)
                            ->update(['status' => AdStatus::Pending->value, 'flag' => 'drugs', 'flag_word' => mb_substr($word, 0, 60), 'updated_at' => now()]);
                    }
                }
            });

        if (! $dry && $found !== []) {
            AdCounters::refreshAll(); // the update above skips the model events that keep the totals
            PageCache::bump();
            Activity::system('ads.drugs_check', 'Sent '.count($found).' live ad(s) with drug words back for checking');
        }

        return $found;
    }
}
