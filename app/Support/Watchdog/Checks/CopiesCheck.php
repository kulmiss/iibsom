<?php

namespace App\Support\Watchdog\Checks;

use App\Enums\AdStatus;
use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Watchdog;
use Illuminate\Support\Facades\DB;

/** Copy spam: many ads in 24 hours with the same photo (photo fingerprint) or the same text. */
final class CopiesCheck implements Check
{
    public function key(): string
    {
        return 'ads.copies';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        $limit = max(2, $limit);
        $since = now()->subDay();
        $statuses = [AdStatus::Live->value, AdStatus::Pending->value];
        $found = [];

        $photos = DB::table('ad_photos')->join('ads', 'ads.id', '=', 'ad_photos.ad_id')
            ->where('ads.created_at', '>=', $since)->whereIn('ads.status', $statuses)->where('ads.is_demo', false)->whereNotNull('ad_photos.phash')
            ->groupBy('ad_photos.phash')->havingRaw('count(distinct ads.id) >= ?', [$limit])
            ->selectRaw('ad_photos.phash, count(distinct ads.id) as total, count(distinct ads.user_id) as sellers, min(ads.id) as first_ad')->limit(20)->get();
        foreach ($photos as $row) {
            $found[] = new Finding("{$this->key()}:photo:{$row->phash}", 'warning', "The same photo is in {$row->total} new ads",
                "{$row->total} ads from the last 24 hours (from {$row->sellers} seller(s)) use the same photo. First: ad #{$row->first_ad}.",
                ['Open the ads and check whether they are real.', 'Reject the copies; ban the seller if it is spam.'],
                ['ads' => (int) $row->total, 'sellers' => (int) $row->sellers], 'ad', (int) $row->first_ad);
        }

        $texts = DB::table('ads')->where('created_at', '>=', $since)->whereIn('status', $statuses)->where('is_demo', false)
            ->whereRaw('char_length(description) >= 30')
            ->groupByRaw('md5(lower(trim(description)))')->havingRaw('count(*) >= ?', [$limit])
            ->selectRaw('md5(lower(trim(description))) as text_hash, count(*) as total, count(distinct user_id) as sellers, min(id) as first_ad')->limit(20)->get();
        foreach ($texts as $row) {
            $found[] = new Finding("{$this->key()}:text:".substr((string) $row->text_hash, 0, 16), 'warning', "The same text is in {$row->total} new ads",
                "{$row->total} ads from the last 24 hours (from {$row->sellers} seller(s)) have exactly the same description. First: ad #{$row->first_ad}.",
                ['Open the ads and check whether they are real.', 'Reject the copies; ban the seller if it is spam.'],
                ['ads' => (int) $row->total, 'sellers' => (int) $row->sellers], 'ad', (int) $row->first_ad);
        }

        return $found;
    }
}
