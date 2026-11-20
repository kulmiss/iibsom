<?php

namespace App\Support\Features;

/**
 * "Launch setup" (PR 106): the recommended ON/OFF for the first days of the live site, in one click.
 * Only the switches listed here change; every other switch keeps what you chose.
 * Decided by the owner on 2026-11-20 / 21 (see DECISIONS.md).
 */
class LaunchSetup
{
    /** @var array<string, array{0: bool, 1: string}> switch => [ON?, why] */
    public const PRESET = [
        // Sign-in
        'login.email' => [true, 'People sign up with their email (needs real email on the live site).'],
        'login.google' => [true, 'The Google button shows only once its keys are set.'],
        'login.phone' => [false, 'No SMS (your decision).'],
        // Free features for launch
        'favourites' => [true, 'Saving ads is free and brings people back.'],
        'saved_searches' => [true, 'Alerts for new ads bring people back every day.'],
        'ad_stats' => [true, 'Sellers see their views; nothing about visitors is kept.'],
        'verified_badge' => [true, 'Trust. Free for everyone; ID photos are deleted after the check.'],
        'social.autopost' => [true, 'Shares new ads to Facebook / Telegram once their keys are set; does nothing without keys.'],
        'diaspora' => [true, 'Help and prices in their money for people abroad.'],
        // Not now
        'chat' => [false, 'Chat stays OFF (your decision).'],
        'seller_ratings' => [false, 'Needs chat.'],
        'boosts' => [false, 'Later, once there are enough visitors.'],
        'shops' => [false, 'Later, together with boosts.'],
        'banner_ads' => [false, 'Later, when you can show visitor numbers.'],
        'price_suggestions' => [false, 'Needs real ads first (about 1–2 months).'],
        'ads.trusted_sellers' => [false, 'Turn ON after the first weeks.'],
        'api.write' => [false, 'Only when the phone app exists.'],
        'app' => [false, 'Only when the phone app exists.'],
        'site.demo_data' => [false, 'Real ads only on the live site.'],
    ];

    /**
     * What one click would change, before anything changes.
     *
     * @return list<array{key: string, label: string, from: bool, to: bool, why: string}>
     */
    public static function changes(FeatureManager $features): array
    {
        $changes = [];
        foreach (self::PRESET as $key => [$on, $why]) {
            if (! $features->exists($key)) {
                continue;
            }
            $now = $features->enabled($key);
            if ($now !== $on) {
                $changes[] = ['key' => $key, 'label' => (string) config('features')[$key]['label'], 'from' => $now, 'to' => $on, 'why' => $why];
            }
        }

        return $changes;
    }
}
