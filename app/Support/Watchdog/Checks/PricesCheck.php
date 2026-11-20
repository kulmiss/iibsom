<?php

namespace App\Support\Watchdog\Checks;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\WatchdogProblem;
use App\Support\Ads\PriceHint;
use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Watchdog;

/**
 * "Please check": new ads priced under half of the usual low price of similar ads (the same rule as the
 * ad page's amber note, PR 83). Only a list for staff: nothing is hidden and nobody is banned.
 */
final class PricesCheck implements Check
{
    private const MAX_ADS = 200;

    public function key(): string
    {
        return 'ads.prices';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        $found = [];
        // Ads staff already looked at ("Mark as fixed") are not asked about again.
        $checked = array_flip(WatchdogProblem::where('key', 'like', 'ads.prices:%')->where('fixed_how', 'staff')->pluck('key')->all());
        $ads = Ad::with('category')->whereIn('status', [AdStatus::Live, AdStatus::Pending])->where('is_demo', false)
            ->where('created_at', '>=', now()->subDays(2))->whereNotNull('price')->where('price', '>', 0)
            ->latest('id')->limit(self::MAX_ADS)->get();

        foreach ($ads as $ad) {
            $hint = PriceHint::for($ad->category, $ad->condition, $ad->fields ?? [], $ad->id);
            if (isset($checked["{$this->key()}:{$ad->id}"]) || $hint === null || $ad->price >= $hint['low'] / 2) {
                continue;
            }
            $found[] = new Finding("{$this->key()}:{$ad->id}", 'info', "Please check: a very low price (ad #{$ad->id})",
                "Ad #{$ad->id} asks \${$ad->price}; similar ads usually ask \${$hint['low']} – \${$hint['high']}. Very low prices are a common trick.",
                ['Open the ad and look at the photos and the text.', 'Reject it if it looks fake; otherwise mark this as fixed.'],
                ['price' => (int) $ad->price, 'usual_low' => $hint['low']], 'ad', $ad->id);
        }

        return $found;
    }
}
