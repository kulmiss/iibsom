<?php

namespace App\Support\Watchdog\Checks;

use App\Models\Ad;
use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Watchdog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Opens the home page and the newest live ad the way a visitor does (from the scheduler, never in a visitor's request).
 * Ad views are counted by a separate beacon, so this does not add views. Slow = two checks in a row (one slow
 * moment is normal); not opening = urgent at once.
 */
final class SpeedCheck implements Check
{
    public function key(): string
    {
        return 'health.speed';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        $pages = ['home' => ['Home page', url('/')]];
        $ad = Ad::live()->with(['category:id,slug', 'city:id,slug'])->latest('id')->first();
        if ($ad !== null) {
            $pages['ad'] = ['Newest ad page', $ad->url()];
        }

        $found = [];
        foreach ($pages as $part => [$name, $url]) {
            $start = microtime(true);
            try {
                $status = Http::timeout(15)->connectTimeout(5)->withHeaders(['User-Agent' => 'iibsom-watchdog'])->get($url)->status();
            } catch (Throwable) {
                $status = 0;
            }
            $ms = (int) round((microtime(true) - $start) * 1000);
            self::rememberForToday($ms);

            if ($status === 0 || $status >= 500) {
                $found[] = new Finding("{$this->key()}:{$part}:down", 'urgent', "{$name} does not open",
                    $status === 0 ? "{$name} did not answer within 15 seconds." : "{$name} answers with error {$status}. Visitors see an error page.",
                    ['Open the site on your phone to see what visitors see.', 'Open Admin → Health and the Watchdog errors card.', 'Look at storage/logs/laravel.log on the server.'],
                    ['status' => $status]);

                continue;
            }

            $slowKey = "watchdog.speed.slow.{$part}";
            if ($ms > $limit) {
                $times = (int) Cache::get($slowKey, 0) + 1;
                Cache::put($slowKey, $times, now()->addHour());
                if ($times >= 2) {
                    $found[] = new Finding("{$this->key()}:{$part}:slow", 'warning', "{$name} is slow",
                        "{$name} took {$ms} ms to open (your limit is {$limit} ms), two checks in a row.",
                        ['Check whether the server is busy (CPU, memory).', 'Check that the page cache switch is ON.'],
                        ['ms' => $ms]);
                }
            } else {
                Cache::forget($slowKey);
            }
        }

        return $found;
    }

    /** Average speed of the day, for the daily digest (cache only, two days). */
    private static function rememberForToday(int $ms): void
    {
        $key = 'watchdog.speed.day.'.now()->timezone(config('watchdog.timezone'))->format('Y-m-d');
        $day = (array) Cache::get($key, ['sum' => 0, 'count' => 0]);
        Cache::put($key, ['sum' => (int) $day['sum'] + $ms, 'count' => (int) $day['count'] + 1], now()->addDays(2));
    }

    public static function averageForDay(string $day): ?int
    {
        $data = (array) Cache::get("watchdog.speed.day.{$day}", ['sum' => 0, 'count' => 0]);

        return (int) $data['count'] > 0 ? (int) round($data['sum'] / $data['count']) : null;
    }
}
