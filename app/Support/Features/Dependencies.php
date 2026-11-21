<?php

namespace App\Support\Features;

/**
 * Switches that only work while another switch is ON (PR 106).
 * The site already checks these in its pages; this list lets the admin say so, e.g. "Needs Chat (OFF)",
 * so staff don't wonder why a switch that is ON shows nothing.
 * Only switches that need ALL of their list are here (a switch that works partly without the other is left out).
 */
class Dependencies
{
    /** @var array<string, list<string>> switch => switches it needs */
    public const NEEDS = [
        'ads.guest_reports' => ['ads.reports'],
        'chat.ad_box' => ['chat'],
        'chat.quick_replies' => ['chat'],
        'chat.offers' => ['chat'],
        'seller_ratings' => ['chat'],
        'ads.card_stars' => ['seller_ratings'],
        'ratings.replies' => ['seller_ratings'],
        'ratings.likes' => ['seller_ratings'],
        'ads.card_years' => ['sellers.badges'],
        'ads.recently_viewed' => ['recommendations'],
        'home.recommended' => ['recommendations'],
        'sellers.page_plus' => ['sellers.page'],
        'sellers.follow' => ['sellers.page'],
        'help.how_to_sell' => ['help.centre'],
        'help.how_we_keep_safe' => ['help.centre'],
        'ads.card_save' => ['favourites'],
        'account.saved_count' => ['favourites'],
        'favourites.price_drop' => ['favourites', 'notifications'],
        'saved_searches.manage' => ['saved_searches'],
        'contact.call_back' => ['notifications'],
        'home.featured' => ['boosts'],
        'home.popular_now' => ['ad_stats'],
        'ads.public_views' => ['ad_stats'],
        'pwa.install_prompt' => ['pwa'],
        'search.districts_all' => ['search.more_filters'],
    ];

    /** Short names for the badge ("Needs Chat (OFF)"); others use the switch's own label. */
    public const SHORT = [
        'chat' => 'Chat',
        'seller_ratings' => 'Seller ratings',
        'favourites' => 'Saved ads',
        'saved_searches' => 'Saved searches',
        'ad_stats' => 'Ad statistics',
        'boosts' => 'Paid boosts',
        'notifications' => 'Notifications bell',
        'recommendations' => 'Recommended for you',
        'help.centre' => 'Help centre',
        'sellers.page' => 'Seller pages',
        'sellers.badges' => 'Seller badges',
        'ads.reports' => 'Report an ad',
        'pwa' => 'Installable website',
        'search.more_filters' => 'More search filters',
    ];

    /**
     * The first switch this one is waiting for (OFF itself, or OFF further down the chain), or null.
     * "Seller stars on cards" → needs "Seller ratings" (ON) → needs "Chat" (OFF) → waiting for Chat.
     *
     * @param  array<string, bool>  $enabled  every switch => ON?
     * @return array{key: string, name: string}|null
     */
    public static function waitingFor(string $key, array $enabled, int $depth = 0): ?array
    {
        if ($depth > 5) {
            return null; // never loops, even if the list above were wrong
        }
        foreach (self::NEEDS[$key] ?? [] as $need) {
            if (! ($enabled[$need] ?? false)) {
                return ['key' => $need, 'name' => self::name($need)];
            }
            if ($deeper = self::waitingFor($need, $enabled, $depth + 1)) {
                return $deeper;
            }
        }

        return null;
    }

    public static function name(string $key): string
    {
        return self::SHORT[$key] ?? (string) (config('features')[$key]['label'] ?? $key);
    }
}
