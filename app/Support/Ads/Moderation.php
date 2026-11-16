<?php

namespace App\Support\Ads;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\User;
use App\Support\Admin\Activity;
use InvalidArgumentException;

/** Approve / reject ads (used by the admin, one by one or in bulk). */
final class Moderation
{
    public static function approve(Ad $ad, User $by): void
    {
        if (! in_array($ad->status, [AdStatus::Pending, AdStatus::Rejected], true)) {
            throw new InvalidArgumentException('Only ads waiting for approval (or rejected) can be approved.');
        }

        $ad->forceFill([
            'status' => AdStatus::Live,
            'rejection_reason' => null,
            'published_at' => $ad->published_at ?? now(),
            'expires_at' => feature('ads.expiry') ? now()->addDays(setting('ads.expiry_days')) : null,
            'reviewed_by' => $by->id,
            'reviewed_at' => now(),
        ])->save();

        Activity::log('ad.approved', "Approved ad #{$ad->id} “{$ad->title}”", $ad);
    }

    public static function reject(Ad $ad, User $by, string $reason): void
    {
        $ad->forceFill([
            'status' => AdStatus::Rejected,
            'rejection_reason' => mb_substr(trim($reason), 0, 300),
            'reviewed_by' => $by->id,
            'reviewed_at' => now(),
        ])->save();

        Activity::log('ad.rejected', "Rejected ad #{$ad->id} “{$ad->title}”: {$reason}", $ad, ['reason' => $reason]);
    }
}
