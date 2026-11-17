<?php

namespace App\Support\Money;

use App\Models\Ad;
use App\Models\AdBoost;
use App\Models\Payment;
use App\Support\Admin\Activity;
use App\Support\Cache\PageCache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Approving or rejecting a boost payment: all-or-nothing, never twice. */
class Boosts
{
    /** @return array{level: int, days: int, price_cents: int} */
    public static function package(string $key): array
    {
        $package = config('boosts.packages.'.$key);
        if (! is_array($package)) {
            throw new RuntimeException("Unknown boost package [{$key}].");
        }

        return ['level' => (int) $package['level'], 'days' => (int) $package['days'], 'price_cents' => (int) $package['price_cents']];
    }

    /** Name of a package (by key or level), in the current language. */
    public static function label(string|int $package): string
    {
        return match ($package) {
            'top', 1 => __('ads.boost.top'),
            'vip', 2 => __('ads.boost.vip'),
            'diamond', 3 => __('ads.boost.diamond'),
            default => '',
        };
    }

    public static function approve(Payment $payment, int $staffId): void
    {
        DB::transaction(function () use ($payment, $staffId) {
            // Lock the row: two staff pressing Approve at the same time can't both win.
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                throw new RuntimeException('This payment was already decided.');
            }
            $package = self::package($locked->package);

            $locked->forceFill(['status' => 'approved', 'reviewed_by' => $staffId, 'reviewed_at' => now()])->save();

            Ledger::post([
                ['account' => 'cash:'.$locked->method, 'debit' => $locked->amount_cents],
                ['account' => 'revenue:boosts', 'credit' => $locked->amount_cents],
            ], $locked->is_test, $locked->id, "Boost {$locked->package} for ad #{$locked->ad_id}", $locked->currency);

            $ad = $locked->ad_id ? Ad::whereKey($locked->ad_id)->lockForUpdate()->first() : null;
            if ($ad !== null) {
                // Still boosted: add the days to the end and keep the higher level.
                $active = $ad->boosted_until !== null && $ad->boosted_until->isFuture();
                $start = $active ? $ad->boosted_until->copy() : now();
                $end = $start->copy()->addDays($package['days']);
                $ad->forceFill([
                    'boost_level' => max($active ? $ad->boost_level : 0, $package['level']),
                    'boosted_until' => $end,
                ])->save();
                AdBoost::create(['ad_id' => $ad->id, 'payment_id' => $locked->id, 'package' => $locked->package, 'level' => $package['level'], 'starts_at' => $start, 'ends_at' => $end]);
            }
        });
        PageCache::bump();
        Activity::log('payment.approved', 'Approved '.Payment::money($payment->amount_cents).' '.($payment->is_test ? 'TEST ' : '')."payment #{$payment->id} ({$payment->package} boost)", $payment->user);
    }

    public static function reject(Payment $payment, int $staffId, string $reason): void
    {
        DB::transaction(function () use ($payment, $staffId, $reason) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') {
                throw new RuntimeException('This payment was already decided.');
            }
            $locked->forceFill(['status' => 'rejected', 'reason' => $reason, 'reviewed_by' => $staffId, 'reviewed_at' => now()])->save();
        });
        Activity::log('payment.rejected', "Rejected payment #{$payment->id}: {$reason}", $payment->user);
    }

    /** Boosts that ended: back to a normal ad (scheduled every minute). */
    public static function endExpired(): int
    {
        $count = Ad::query()->where('boost_level', '>', 0)->where('boosted_until', '<=', now())->update(['boost_level' => 0]);
        if ($count > 0) {
            PageCache::bump();
        }

        return $count;
    }
}
