<?php

namespace App\Support\Safety;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\ScamNumber;
use App\Models\User;
use App\Support\Cache\PageCache;
use App\Support\Phone\SomaliPhone;
use App\Support\Watchdog\Signals;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Scam number warning list (PR 109, switch "safety.scam_numbers").
 * - Only staff mark a number, with a reason and a proof note. Nothing is ever added automatically.
 * - A listed number cannot be used to post, and its ads cannot go live (they wait for staff).
 * - Buyers who search for or check the number see "Reported for scams" — never a name.
 */
class ScamNumbers
{
    /** Flag on an ad that waits because its number is on the list. */
    public const FLAG = 'scam_number';

    private const CACHE_KEY = 'scam_numbers.active';

    public static function on(): bool
    {
        return feature('safety.scam_numbers');
    }

    /** @return array<string, true> every listed number (+252…) => true; cached until the list changes */
    public static function active(): array
    {
        /** @var array<string, true> */
        return Cache::rememberForever(self::CACHE_KEY, fn () => ScamNumber::active()->pluck('phone')->mapWithKeys(fn ($p) => [(string) $p => true])->all());
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        PageCache::bump();
    }

    public static function listed(?string $phone): bool
    {
        $phone = SomaliPhone::normalize($phone);

        return self::on() && $phone !== null && isset(self::active()[$phone]);
    }

    /**
     * Somali mobile numbers written inside a text ("call 061 512 3456", "+252615123456"), normalized.
     *
     * @return list<string>
     */
    public static function numbersIn(string $text): array
    {
        preg_match_all('/\+?\d[\d \-.()]{7,18}\d/u', $text, $m);
        $found = [];
        foreach ($m[0] as $candidate) {
            if (($n = SomaliPhone::normalize($candidate)) !== null) {
                $found[$n] = true;
            }
        }

        return array_keys($found);
    }

    /** The first listed number used by this seller or written in these texts, or null. */
    public static function firstListed(?User $user, string ...$texts): ?string
    {
        if (! self::on() || self::active() === []) {
            return null;
        }
        $candidates = array_merge([SomaliPhone::normalize($user?->phone)], ...array_map(fn (string $t) => self::numbersIn($t), $texts));
        foreach ($candidates as $phone) {
            if ($phone !== null && isset(self::active()[$phone])) {
                return $phone;
            }
        }

        return null;
    }

    public static function blocksAd(Ad $ad): bool
    {
        return self::firstListed($ad->user, (string) $ad->title, (string) $ad->description) !== null;
    }

    /** Someone tried to post with a listed number: the watchdog hears of it (no number, no name in the alert). */
    public static function postTried(User $user): void
    {
        Log::channel('security')->warning('scam_number.post_refused', ['user_id' => $user->id]);
        Signals::hit('scam_post_try');
    }

    /**
     * After a number is marked: its live ads (seller's phone, or the number written in the text) wait for staff.
     *
     * @return int how many ads went back to review
     */
    public static function sendAdsToReview(string $phone): int
    {
        $userIds = User::where('phone', $phone)->pluck('id')->flip();
        $moved = 0;
        // Numbers in ad texts are written in many ways (spaces, dashes, +252, 0…), so every live ad's text is
        // read once here. Marking a number is a rare staff action; this takes seconds even with many ads.
        Ad::query()->where('status', AdStatus::Live)->select(['id', 'user_id', 'title', 'description'])
            ->lazyById(500)
            ->each(function (Ad $row) use ($phone, $userIds, &$moved): void {
                if ($userIds->has($row->user_id) || in_array($phone, self::numbersIn($row->title.' '.$row->description), true)) {
                    Ad::whereKey($row->id)->first()?->forceFill(['status' => AdStatus::Pending, 'flag' => self::FLAG])->save();
                    $moved++;
                }
            });

        return $moved;
    }
}
