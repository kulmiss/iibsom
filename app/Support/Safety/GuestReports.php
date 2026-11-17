<?php

namespace App\Support\Safety;

use App\Models\Ad;
use App\Models\Report;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Reports from people who are not signed in (switch "ads.guest_reports").
 * Protection WITHOUT storing anything about the visitor (no IP, no cookie):
 *  - a hidden bot trap field and a signed time stamp in the form (too fast or too old = a bot),
 *  - a rate limit by a keyed hash of the IP that lives only minutes in the cache (route throttle),
 *  - at most PER_AD_DAY guest reports per ad per day, the same report again = +1 on the old one,
 *  - a small sum to answer ("3 + 4 = ?") only while many guest reports arrive (UNDER_ATTACK per hour).
 */
class GuestReports
{
    public const MIN_SECONDS = 3;

    public const MAX_SECONDS = 6 * 3600;

    public const PER_AD_DAY = 20;

    public const UNDER_ATTACK = 60;

    /** "time.signature" put in the form when the page is made. */
    public static function token(?int $time = null): string
    {
        $time ??= now()->getTimestamp();

        return $time.'.'.self::sign((string) $time);
    }

    public static function tokenOk(string $token): bool
    {
        [$time, $signature] = array_pad(explode('.', $token, 2), 2, '');
        if (! ctype_digit($time) || ! hash_equals(self::sign($time), $signature)) {
            return false;
        }
        $age = now()->getTimestamp() - (int) $time;

        return $age >= self::MIN_SECONDS && $age <= self::MAX_SECONDS;
    }

    /**
     * The small sum for this form: two numbers from 1 to 9, made from the token (nothing stored).
     *
     * @return array{0: int, 1: int}
     */
    public static function sum(string $token): array
    {
        $h = self::sign('sum|'.$token);

        return [hexdec($h[0]) % 9 + 1, hexdec($h[1]) % 9 + 1];
    }

    public static function sumOk(string $token, mixed $answer): bool
    {
        [$a, $b] = self::sum($token);

        return is_numeric($answer) && (int) $answer === $a + $b;
    }

    /** Many guest reports in this hour (all ads): ask for the sum. */
    public static function underAttack(): bool
    {
        return (int) Cache::get(self::hourKey(), 0) >= self::UNDER_ATTACK;
    }

    /** Count every guest report try (also dropped ones) for the hour. Only a number, nothing about who. */
    public static function countTry(): void
    {
        $key = self::hourKey();
        Cache::add($key, 0, 7200);
        Cache::increment($key);
    }

    /**
     * Save the report, or add +1 to the same one sent before. Returns false when the ad
     * already has too many guest reports today (then nothing is saved; the guest still sees "thanks").
     */
    public static function store(Ad $ad, string $reason, ?string $details, ?string $contact): bool
    {
        $today = Report::where('ad_id', $ad->id)->whereNull('user_id')->where('created_at', '>=', now()->subDay())->sum('times');
        if ($today >= self::PER_AD_DAY) {
            return false;
        }

        $fingerprint = hash('sha256', $ad->id.'|'.$reason.'|'.Str::lower(Str::squish((string) $details)));
        $same = Report::where('ad_id', $ad->id)->where('fingerprint', $fingerprint)->whereNull('user_id')
            ->where('status', 'waiting')->where('created_at', '>=', now()->subDay())->first();
        if ($same !== null) {
            $same->increment('times');
            if ($contact !== null && $same->contact === null) {
                $same->forceFill(['contact' => $contact])->save();
            }

            return true;
        }

        $report = new Report(['reason' => $reason, 'details' => $details]);
        $report->forceFill(['ad_id' => $ad->id, 'user_id' => null, 'contact' => $contact, 'fingerprint' => $fingerprint, 'status' => 'waiting'])->save();

        return true;
    }

    /** Contact details are only for answering: removed 30 days after the report was handled. */
    public static function pruneContacts(): int
    {
        return Report::whereNotNull('contact')->where('status', '!=', 'waiting')
            ->where('handled_at', '<', now()->subDays(30))->update(['contact' => null]);
    }

    private static function sign(string $value): string
    {
        return hash_hmac('sha256', 'guest-report|'.$value, (string) config('app.key'));
    }

    private static function hourKey(): string
    {
        return 'guest-reports:'.now()->format('YmdH');
    }
}
