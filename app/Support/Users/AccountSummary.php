<?php

namespace App\Support\Users;

use App\Enums\AdStatus;
use App\Models\Conversation;
use App\Models\User;
use App\Support\Ads\AdStats;
use Illuminate\Support\Facades\DB;

/**
 * The numbers on the My account dashboard (PR 79, switch "account.dashboard"). Only the person's own data;
 * a few small counting queries, nothing kept.
 */
class AccountSummary
{
    /** @return array{live: int, waiting: int, views: ?int, contacts: ?int, saved: ?int, unread: ?int, followers: ?int} */
    public static function for(User $user): array
    {
        $counts = $user->ads()->toBase()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $live = (int) ($counts[AdStatus::Live->value] ?? 0);

        $views = $contacts = null;
        if (feature('ad_stats')) {
            $ids = array_values(array_map('intval', $user->ads()->where('status', AdStatus::Live->value)->pluck('id')->all()));
            $totals = AdStats::totals($ids);
            $views = array_sum(array_column($totals, 'views'));
            $contacts = array_sum(array_map(fn (array $t) => $t['phone'] + $t['whatsapp'] + $t['call'], $totals));
        }

        return [
            'live' => $live,
            'waiting' => (int) ($counts[AdStatus::Pending->value] ?? 0),
            'views' => $views,
            'contacts' => $contacts,
            'saved' => feature('favourites') ? $user->favourites()->count() : null,
            'unread' => feature('chat') ? Conversation::unreadCount($user) : null,
            'followers' => feature('sellers.page') && feature('sellers.follow') ? (int) DB::table('follows')->where('seller_id', $user->id)->count() : null,
        ];
    }
}
