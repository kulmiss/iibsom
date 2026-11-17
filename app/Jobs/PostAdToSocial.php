<?php

namespace App\Jobs;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\SocialPost;
use App\Support\Social\SocialPostFailed;
use App\Support\Social\SocialPosts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Share one ad on one network (switch "social.autopost"). Tried 3 times
 * (1 and 5 minutes apart). A failure is written on the row and the log;
 * it never touches the ad or the seller.
 */
class PostAdToSocial implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public int $adId, public string $network) {}

    public function handle(): void
    {
        $row = SocialPost::where('ad_id', $this->adId)->where('network', $this->network)->first();
        if ($row === null || $row->status === 'posted') {
            return; // never twice
        }

        $ad = Ad::with(['category', 'city', 'user'])->find($this->adId);
        $network = SocialPosts::network($this->network);
        // Things may have changed while it waited: switched off, keys removed, ad taken down.
        $skip = match (true) {
            ! feature('social.autopost') => 'The auto-post switch is off.',
            $network === null || ! $network->isConfigured() => 'This network has no keys.',
            $ad === null || $ad->status !== AdStatus::Live || $ad->user->isBanned() => 'The ad is no longer live.',
            default => null,
        };
        if ($skip !== null) {
            $row->forceFill(['status' => 'skipped', 'error' => $skip])->save();

            return;
        }

        $row->increment('attempts');
        try {
            $id = $network->post(SocialPosts::text($ad), $ad->url());
        } catch (SocialPostFailed $e) {
            $row->forceFill(['status' => 'failed', 'error' => $e->getMessage()])->save();
            Log::warning('social.post_failed', ['ad_id' => $this->adId, 'network' => $this->network, 'error' => $e->getMessage()]);
            throw $e; // the queue tries again later
        }

        $row->forceFill(['status' => 'posted', 'remote_id' => mb_substr($id, 0, 100), 'error' => null, 'posted_at' => now()])->save();
        Log::info('social.posted', ['ad_id' => $this->adId, 'network' => $this->network]);
    }

    public function failed(?Throwable $e): void
    {
        // After the last try: keep the reason on the row (already safe: no keys in it).
        SocialPost::where('ad_id', $this->adId)->where('network', $this->network)->where('status', '!=', 'posted')
            ->update(['status' => 'failed']);
    }
}
