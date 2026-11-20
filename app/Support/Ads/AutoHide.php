<?php

namespace App\Support\Ads;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Report;
use App\Support\Admin\Activity;
use App\Support\Cache\PageCache;
use App\Support\Notify\Inbox;
use Illuminate\Support\Facades\DB;

/**
 * Hide an ad after reports (PR 89, switch "reports.auto_hide", setting "reports.auto_hide_at").
 *
 * When this many DIFFERENT signed-in people (not the seller, not banned) have waiting reports on a live ad, the ad
 * goes back to "waiting for approval" with flag "reports". Nothing is deleted and nobody is banned: staff look at
 * it and either approve it (the reports are closed as wrong) or remove it. Guest reports do not count (anyone could
 * send many). The seller is told in the bell.
 */
final class AutoHide
{
    public const FLAG = 'reports';

    /** @return int how many different people reported it (waiting, signed in) */
    public static function reporters(Ad $ad): int
    {
        return (int) Report::query()->where('reports.ad_id', $ad->id)->where('reports.status', 'waiting')
            ->whereNotNull('reports.user_id')->where('reports.user_id', '!=', $ad->user_id)
            ->join('users', 'users.id', '=', 'reports.user_id')->whereNull('users.banned_at')
            ->distinct()->count('reports.user_id');
    }

    /** Check after a new report. Returns true when the ad was hidden now. */
    public static function check(Ad $ad): bool
    {
        if (! feature('reports.auto_hide')) {
            return false;
        }
        $limit = max(2, (int) setting('reports.auto_hide_at', 3));
        $count = self::reporters($ad);
        if ($count < $limit) {
            return false;
        }

        // Only a live ad, and only once (two reports at the same moment hide it one time).
        $hidden = DB::table('ads')->where('id', $ad->id)->where('status', AdStatus::Live->value)
            ->update(['status' => AdStatus::Pending->value, 'flag' => self::FLAG, 'flag_word' => (string) $count, 'updated_at' => now()]);
        if ($hidden === 0) {
            return false;
        }
        $ad->refresh();
        // What a normal status change does through the model events: live-ad counts and the page cache.
        AdCounters::refreshFor($ad);
        PageCache::adChanged($ad->id);

        Inbox::add($ad->user, 'ad_hidden', $ad, ['title' => $ad->title]);
        Activity::system('ad.auto_hidden', "Hid ad #{$ad->id} “{$ad->title}” after reports from {$count} people (waits for a check)", $ad, ['reporters' => $count, 'limit' => $limit]);

        return true;
    }
}
