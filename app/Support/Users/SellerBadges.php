<?php

namespace App\Support\Users;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Small trust signs next to a seller's name (switch "sellers.badges"):
 * - "2 years on iibsom" / "5 months on iibsom" (from the day the account was made),
 * - "Replies fast": while chat is ON, the seller answered buyers' first messages within an hour
 *   (middle value of at least 3 answers in the last 60 days). Worked out from times only; no text is read.
 */
class SellerBadges
{
    public const FAST_MINUTES = 60;

    public const MIN_ANSWERS = 3;

    public const DAYS = 60;

    /** @return array{unit: 'years'|'months', count: int}|null Under one month: nothing. */
    public static function tenure(User $user): ?array
    {
        if ($user->created_at === null) {
            return null;
        }
        $months = (int) floor($user->created_at->diffInMonths(now()));
        if ($months >= 12) {
            return ['unit' => 'years', 'count' => intdiv($months, 12)];
        }

        return $months >= 1 ? ['unit' => 'months', 'count' => $months] : null;
    }

    /** Kept for 6 hours per seller (one small query). */
    public static function repliesFast(User $user): bool
    {
        if (! feature('chat')) {
            return false;
        }

        return (bool) Cache::remember('seller-fast:'.$user->id, 6 * 3600, function () use ($user) {
            $minutes = self::medianReplyMinutes($user);

            return $minutes !== null && $minutes <= self::FAST_MINUTES;
        });
    }

    /** Middle answer time in minutes, or null with fewer than 3 answers. */
    public static function medianReplyMinutes(User $user): ?float
    {
        $rows = DB::table('messages')
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->where('conversations.seller_id', $user->id)
            ->where('conversations.last_message_at', '>=', now()->subDays(self::DAYS))
            ->orderBy('messages.conversation_id')->orderBy('messages.id')
            ->limit(3000)
            ->get(['messages.conversation_id', 'messages.sender_id', 'messages.created_at']);

        $waits = [];
        $asked = [];
        $done = [];
        foreach ($rows as $m) {
            $c = $m->conversation_id;
            if (isset($done[$c])) {
                continue;
            }
            if ((int) $m->sender_id !== $user->id) {
                $asked[$c] ??= strtotime((string) $m->created_at);
            } elseif (isset($asked[$c])) {
                $waits[] = max(0, strtotime((string) $m->created_at) - $asked[$c]) / 60;
                $done[$c] = true;
            }
        }
        if (count($waits) < self::MIN_ANSWERS) {
            return null;
        }
        sort($waits);
        $n = count($waits);

        return $n % 2 ? $waits[intdiv($n, 2)] : ($waits[$n / 2 - 1] + $waits[$n / 2]) / 2;
    }
}
