<?php

namespace App\Support\Watchdog;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Short-lived counters for the Watchdog (PR 95): failed sign-ins, banned people trying again.
 * Cache only, in 5-minute buckets that expire within 24 hours. Only the kind of thing is counted:
 * never an IP, an email or who it was.
 */
final class Signals
{
    public const NAMES = ['admin_login_failed', 'login_failed', 'banned_retry'];

    public static function hit(string $name): void
    {
        try {
            if (! in_array($name, self::NAMES, true) || ! feature('watchdog')) {
                return;
            }
            $key = "watchdog.signal.{$name}.".intdiv(now()->getTimestamp(), 300);
            Cache::add($key, 0, now()->addDay());
            Cache::increment($key);
        } catch (Throwable) {
            // Counting must never break a sign-in.
        }
    }

    public static function recent(string $name, int $minutes): int
    {
        $now = now()->getTimestamp();
        $total = 0;
        for ($t = $now; $t > $now - $minutes * 60; $t -= 300) {
            $total += (int) Cache::get("watchdog.signal.{$name}.".intdiv($t, 300), 0);
        }

        return $total;
    }
}
