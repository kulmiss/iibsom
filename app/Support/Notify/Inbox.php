<?php

namespace App\Support\Notify;

use App\Models\Ad;
use App\Models\InboxItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The notifications bell (switch "notifications"). Items keep only the words needed for the text
 * (ad title, saved-search name, prices); the text itself is made in the reader's language when shown.
 * Each person chooses what they get (Account → Notifications); null settings = everything ON.
 */
final class Inbox
{
    /** Bell kinds. */
    public const KINDS = ['ad_approved', 'ad_rejected', 'ad_hidden', 'saved_search', 'price_drop', 'call_back', 'followed_ad'];

    /** Emails people can turn off ("saved_search" is the daily alert that already existed). */
    public const EMAILS = ['saved_search', 'call_back'];

    public const KEEP_DAYS = 180;

    /** @return array<string, bool> */
    public static function settings(User $user): array
    {
        $saved = is_array($user->notify_settings) ? $user->notify_settings : [];
        $all = [];
        foreach (self::KINDS as $kind) {
            $all['bell.'.$kind] = (bool) ($saved['bell.'.$kind] ?? true);
        }
        foreach (self::EMAILS as $kind) {
            $all['email.'.$kind] = (bool) ($saved['email.'.$kind] ?? true);
        }

        return $all;
    }

    public static function wants(User $user, string $kind, string $channel = 'bell'): bool
    {
        return self::settings($user)[$channel.'.'.$kind] ?? false;
    }

    /** @param  array<string, mixed>  $data */
    public static function add(User $user, string $kind, ?Ad $ad = null, array $data = []): void
    {
        if (! feature('notifications') || $user->isBanned() || ! self::wants($user, $kind)) {
            return;
        }
        InboxItem::create(['user_id' => $user->id, 'kind' => $kind, 'ad_id' => $ad?->id, 'data' => $data, 'created_at' => now()]);
    }

    /** Unread count for the bell (stops counting at 100). */
    public static function unread(User $user): int
    {
        return DB::table('inbox_items')->where('user_id', $user->id)->whereNull('read_at')->limit(100)->count();
    }

    /**
     * A saved ad got cheaper: tell everyone who saved it (and wants to know). One insert per 500 people.
     * Not again for the same ad within a day (a seller changing the price twice is one message).
     */
    public static function priceDrop(Ad $ad, int $from, int $to): void
    {
        if (! feature('notifications') || ! feature('favourites') || ! feature('favourites.price_drop') || $to >= $from || ! $ad->isLive()) {
            return;
        }
        $recent = InboxItem::where('kind', 'price_drop')->where('ad_id', $ad->id)->where('created_at', '>=', now()->subDay())->pluck('user_id')->flip();
        DB::table('favourites')->where('ad_id', $ad->id)->orderBy('user_id')
            ->chunk(500, function ($rows) use ($ad, $from, $to, $recent) {
                $users = User::whereIn('id', $rows->pluck('user_id'))->whereNull('banned_at')->get(['id', 'notify_settings', 'banned_at']);
                $insert = [];
                foreach ($users as $user) {
                    if ($user->id !== $ad->user_id && ! $recent->has($user->id) && self::wants($user, 'price_drop')) {
                        $insert[] = ['user_id' => $user->id, 'kind' => 'price_drop', 'ad_id' => $ad->id, 'created_at' => now(),
                            'data' => json_encode(['title' => $ad->title, 'from' => $from, 'to' => $to])];
                    }
                }
                if ($insert) {
                    DB::table('inbox_items')->insert($insert);
                }
            });
    }

    /** A followed seller has a new live ad (first time live only): tell their followers who want to know. */
    public static function followedAd(Ad $ad): void
    {
        if (! feature('notifications') || ! feature('sellers.follow') || ! $ad->isLive() || $ad->is_demo) {
            return;
        }
        $seller = $ad->user;
        DB::table('follows')->where('seller_id', $ad->user_id)->orderBy('follower_id')
            ->chunk(500, function ($rows) use ($ad, $seller) {
                $users = User::whereIn('id', $rows->pluck('follower_id'))->whereNull('banned_at')->get(['id', 'notify_settings', 'banned_at']);
                $insert = [];
                foreach ($users as $user) {
                    if (self::wants($user, 'followed_ad')) {
                        $insert[] = ['user_id' => $user->id, 'kind' => 'followed_ad', 'ad_id' => $ad->id, 'created_at' => now(),
                            'data' => json_encode(['title' => $ad->title, 'seller' => $seller->name])];
                    }
                }
                if ($insert) {
                    DB::table('inbox_items')->insert($insert);
                }
            });
    }

    /** Text of one item in the current language (escaped by Blade when shown). */
    public static function text(InboxItem $item): string
    {
        $d = $item->data ?? [];
        $money = fn ($v) => '$'.number_format((int) $v);

        return match ($item->kind) {
            'ad_approved' => __('notify.text.ad_approved', ['title' => $d['title'] ?? '']),
            'ad_rejected' => __('notify.text.ad_rejected', ['title' => $d['title'] ?? '', 'reason' => $d['reason'] ?? '']),
            'ad_hidden' => __('notify.text.ad_hidden', ['title' => $d['title'] ?? '']),
            'saved_search' => trans_choice('notify.text.saved_search', (int) ($d['count'] ?? 1), ['count' => (int) ($d['count'] ?? 1), 'label' => $d['label'] ?? '']),
            'price_drop' => __('notify.text.price_drop', ['title' => $d['title'] ?? '', 'from' => $money($d['from'] ?? 0), 'to' => $money($d['to'] ?? 0)]),
            'followed_ad' => __('notify.text.followed_ad', ['seller' => $d['seller'] ?? '', 'title' => $d['title'] ?? '']),
            'call_back' => __('notify.text.call_back', ['name' => $d['name'] ?? '', 'phone' => $d['phone'] ?? '', 'title' => $d['title'] ?? '']),
            default => '',
        };
    }

    /** Where tapping the item goes. */
    public static function url(InboxItem $item): ?string
    {
        $ad = $item->ad;

        return match ($item->kind) {
            'ad_approved', 'price_drop', 'followed_ad' => $ad?->isLive() ? $ad->url() : null,
            'ad_rejected' => feature('ads.my_ads') ? lroute('account.ads') : null,
            'ad_hidden' => feature('ads.my_ads') ? lroute('account.ads', ['tab' => 'pending']) : null,
            'saved_search' => feature('saved_searches') ? lroute('searches') : null,
            'call_back' => isset($item->data['phone']) ? 'tel:'.preg_replace('/[^\d+]/', '', (string) $item->data['phone']) : null,
            default => null,
        };
    }

    public static function icon(string $kind): string
    {
        return match ($kind) {
            'ad_approved' => 'circle-check',
            'ad_rejected' => 'circle-x',
            'ad_hidden' => 'eye-off',
            'saved_search' => 'search',
            'price_drop' => 'trending-down',
            'call_back' => 'phone-call',
            'followed_ad' => 'user-check',
            default => 'bell',
        };
    }
}
