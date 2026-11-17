<?php

namespace App\Support\Social;

use App\Enums\AdStatus;
use App\Jobs\PostAdToSocial;
use App\Models\Ad;
use App\Models\SocialPost;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

/**
 * Share new live ads to the networks that have keys (switch "social.autopost", OFF).
 * Each ad goes to each network at most once (database unique key), in the background.
 */
class SocialPosts
{
    /** @var list<class-string<SocialNetwork>> */
    public const NETWORKS = [TelegramChannel::class, FacebookPage::class];

    /** @return list<SocialNetwork> */
    public static function networks(): array
    {
        return array_map(fn (string $class) => app($class), self::NETWORKS);
    }

    public static function network(string $key): ?SocialNetwork
    {
        foreach (self::networks() as $network) {
            if ($network->key() === $key) {
                return $network;
            }
        }

        return null;
    }

    /** Called when an ad is saved: queue it once per configured network when it has just gone live. */
    public static function queueFor(Ad $ad): void
    {
        if (! feature('social.autopost') || $ad->is_demo || $ad->status !== AdStatus::Live) {
            return;
        }

        foreach (self::networks() as $network) {
            if (! $network->isConfigured()) {
                continue;
            }
            try {
                SocialPost::create(['ad_id' => $ad->id, 'network' => $network->key()]);
            } catch (UniqueConstraintViolationException) {
                continue; // already shared (or waiting): never twice, even after an edit or renew
            }
            DB::afterCommit(fn () => PostAdToSocial::dispatch($ad->id, $network->key()));
        }
    }

    /** The post text, in the site's main language (it is for everyone). */
    public static function text(Ad $ad): string
    {
        $ad->loadMissing(['city', 'category']);
        $price = $ad->price !== null ? '$'.Number::format($ad->price) : __('ads.form.negotiable');

        return implode("\n", array_filter([
            $ad->title,
            __('site.social.line', ['price' => $price, 'city' => $ad->city->label, 'category' => $ad->category->label]),
        ]));
    }
}
