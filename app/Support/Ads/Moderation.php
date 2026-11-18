<?php

namespace App\Support\Ads;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\User;
use App\Support\Admin\Activity;
use App\Support\Notify\Inbox;
use InvalidArgumentException;

/** Approve / reject ads (used by the admin, one by one or in bulk). */
final class Moderation
{
    public static function approve(Ad $ad, User $by): void
    {
        if (! in_array($ad->status, [AdStatus::Pending, AdStatus::Rejected], true)) {
            throw new InvalidArgumentException('Only ads waiting for approval (or rejected) can be approved.');
        }

        $before = is_array($ad->approved_snapshot) ? ($ad->approved_snapshot['price'] ?? null) : null;
        $firstTime = $ad->published_at === null;

        $ad->forceFill([
            'status' => AdStatus::Live,
            'rejection_reason' => null,
            'published_at' => $ad->published_at ?? now(),
            'expires_at' => feature('ads.expiry') ? now()->addDays(setting('ads.expiry_days')) : null,
            'reviewed_by' => $by->id,
            'reviewed_at' => now(),
            'approved_snapshot' => AdSnapshot::take($ad), // "Edited" is measured against this
        ])->save();

        Activity::log('ad.approved', "Approved ad #{$ad->id} “{$ad->title}”", $ad);

        // Notifications (PR 66): tell the seller; people who saved it hear about a lower price.
        Inbox::add($ad->user, 'ad_approved', $ad, ['title' => $ad->title]);
        if ($before !== null && $ad->price !== null) {
            Inbox::priceDrop($ad, (int) $before, (int) $ad->price);
        }
        if ($firstTime) {
            Inbox::followedAd($ad); // followers hear about new ads, not about edits
        }
    }

    public static function reject(Ad $ad, User $by, string $reason): void
    {
        $ad->forceFill([
            'status' => AdStatus::Rejected,
            'rejection_reason' => mb_substr(trim($reason), 0, 300),
            'reviewed_by' => $by->id,
            'reviewed_at' => now(),
        ])->save();

        Inbox::add($ad->user, 'ad_rejected', $ad, ['title' => $ad->title, 'reason' => $ad->rejection_reason]);

        Activity::log('ad.rejected', "Rejected ad #{$ad->id} “{$ad->title}”: {$reason}", $ad, ['reason' => $reason]);
    }
}
