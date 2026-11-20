<?php

namespace App\Support\Watchdog;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Counts errors for the Watchdog (PR 94), in the cache only, in 5-minute buckets that expire within a day.
 * An error is grouped by its kind and place (class + file + line): never its message, the address or who
 * saw it, so nothing personal (no IP, no text a visitor typed) is kept.
 * Costs one or two cache writes, and only when an error happens.
 */
final class ErrorCounter
{
    private const KNOWN = 'watchdog.errors.known';

    private const NEW = 'watchdog.errors.new';

    public static function record(Throwable $e): void
    {
        try {
            if (! feature('watchdog')) {
                return;
            }
            $bucket = self::bucket(now()->getTimestamp());
            Cache::add("watchdog.errors.{$bucket}", 0, now()->addDay());
            Cache::increment("watchdog.errors.{$bucket}");
            $day = now()->timezone(config('watchdog.timezone'))->format('Y-m-d');
            Cache::add("watchdog.errors.day.{$day}", 0, now()->addDays(2));
            Cache::increment("watchdog.errors.day.{$day}");

            $kind = self::kind($e);
            $known = (array) Cache::get(self::KNOWN, []);
            if (! array_key_exists($kind['id'], $known)) {
                $known[$kind['id']] = now()->getTimestamp();
                Cache::put(self::KNOWN, array_slice($known, -500, null, true), now()->addDays(30));
                $new = (array) Cache::get(self::NEW, []);
                $new[$kind['id']] = $kind;
                Cache::put(self::NEW, array_slice($new, -20, null, true), now()->addDay());
            }
        } catch (Throwable) {
            // Counting must never make an error worse.
        }
    }

    /** Errors in the last $minutes (whole 5-minute buckets). */
    public static function recent(int $minutes = 15): int
    {
        $now = now()->getTimestamp();
        $total = 0;
        for ($t = $now; $t > $now - $minutes * 60; $t -= 300) {
            $total += (int) Cache::get('watchdog.errors.'.self::bucket($t), 0);
        }

        return $total;
    }

    public static function today(?string $day = null): int
    {
        return (int) Cache::get('watchdog.errors.day.'.($day ?? now()->timezone(config('watchdog.timezone'))->format('Y-m-d')), 0);
    }

    /** @return list<array{id: string, class: string, place: string}>  New kinds since the last call (then forgotten). */
    public static function takeNew(): array
    {
        $new = (array) Cache::pull(self::NEW, []);

        return array_values($new);
    }

    /** @return array{id: string, class: string, place: string} */
    public static function kind(Throwable $e): array
    {
        $file = str_replace('\\', '/', $e->getFile());
        $base = str_replace('\\', '/', base_path()).'/';
        $place = (str_starts_with($file, $base) ? substr($file, strlen($base)) : basename($file)).':'.$e->getLine();
        $class = $e::class;

        return ['id' => substr(hash('sha256', $class.'|'.$place), 0, 16), 'class' => class_basename($class), 'place' => $place];
    }

    private static function bucket(int $timestamp): int
    {
        return intdiv($timestamp, 300);
    }
}
