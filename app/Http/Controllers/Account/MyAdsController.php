<?php

namespace App\Http\Controllers\Account;

use App\Enums\AdStatus;
use App\Http\Controllers\Ads\PostAdController;
use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Support\Ads\AdForm;
use App\Support\Ads\AdStats;
use App\Support\Cache\PageCache;
use App\Support\Catalog;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * My Ads: the seller's own ads. Edit, mark as sold, renew, delete.
 * Every action checks the ad belongs to the signed-in user (404 if not).
 */
class MyAdsController extends Controller
{
    public const TABS = ['live', 'pending', 'rejected', 'ended'];

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'live';
        $mine = Ad::where('user_id', $this->authUser($request)->id);

        $counts = [];
        foreach (self::TABS as $t) {
            $counts[$t] = $this->tab(clone $mine, $t)->count();
        }

        $ads = $this->tab($mine, $tab)->with(['cover', 'category', 'city'])
            ->latest('updated_at')->paginate(20)->withQueryString();

        $stats = feature('ad_stats') ? AdStats::totals(array_values(array_map('intval', $ads->pluck('id')->all()))) : [];

        return view('account.my-ads', compact('ads', 'tab', 'counts', 'stats'));
    }

    public function edit(Request $request, Ad $ad, Catalog $catalog, PostAdController $post): View
    {
        $this->own($request, $ad);
        abort_if($ad->status === AdStatus::Sold, 404);
        $category = $ad->category;

        return view('ads.post-form', [
            'ad' => $ad,
            'photos' => $ad->photos()->where('status', 'ready')->get()
                ->map(fn ($p) => ['id' => $p->id, 'preview' => $p->url('thumb')])->values()->all(),
            'category' => $category,
            'fields' => $category->allFields(),
            'cities' => $catalog->cities(),
            'areas' => $post->areas(),
            'maxPhotos' => setting('ads.max_photos'),
        ]);
    }

    public function update(Request $request, Ad $ad, AdForm $form): RedirectResponse
    {
        $this->own($request, $ad);
        abort_if($ad->status === AdStatus::Sold, 404);

        $category = $ad->category;
        $form->update($ad, $category, $form->validate($request, $category, $ad));
        Log::info('ad.edited', ['ad_id' => $ad->id, 'user_id' => $ad->user_id]);

        $message = $ad->status === AdStatus::Pending ? __('ads.mine.saved_pending') : __('ads.mine.saved');

        return redirect(lroute('account.ads', ['tab' => $ad->status === AdStatus::Pending ? 'pending' : 'live']))->with('status', $message);
    }

    public function sold(Request $request, Ad $ad): RedirectResponse
    {
        $this->own($request, $ad);
        abort_unless($ad->status === AdStatus::Live, 404);

        $ad->status = AdStatus::Sold;
        $ad->sold_at = now();
        $ad->save();

        return back()->with('status', __('ads.mine.marked_sold'));
    }

    /** Why a seller closes an ad (switch "ads.close_reason"). The two "sold" reasons mark it sold. */
    public const CLOSE_REASONS = ['sold_here', 'sold_elsewhere', 'not_selling', 'other'];

    public const BUMP_DAYS = 7;

    /** Close with a reason: sold (here or elsewhere) = Sold; otherwise ended now (it can be renewed later). */
    public function close(Request $request, Ad $ad): RedirectResponse
    {
        $this->own($request, $ad);
        abort_unless($ad->status === AdStatus::Live, 404);
        $reason = $request->validate(['reason' => ['required', 'in:'.implode(',', self::CLOSE_REASONS)]], [], ['reason' => __('ads.mine.close_reason')])['reason'];

        $ad->closed_reason = $reason;
        if (str_starts_with($reason, 'sold_')) {
            $ad->status = AdStatus::Sold;
            $ad->sold_at = now();
        } else {
            $ad->status = AdStatus::Expired;
            $ad->expires_at = now();
        }
        $ad->save();

        return back()->with('status', __(str_starts_with($reason, 'sold_') ? 'ads.mine.marked_sold' : 'ads.mine.closed'));
    }

    /** Free "move to top" once every 7 days (switch "ads.free_bump"): the ad shows again as new in lists. */
    public function bump(Request $request, Ad $ad): RedirectResponse
    {
        $this->own($request, $ad);
        abort_unless($ad->isLive(), 404);
        if (! self::canBump($ad)) {
            return back()->with('status', __('ads.mine.bump_wait', ['date' => mdate(self::nextBump($ad))]));
        }

        // Atomic: two quick taps (or two tabs) move it only once.
        $done = Ad::whereKey($ad->id)->where('status', AdStatus::Live)
            ->where('published_at', '<=', now()->subDays(self::BUMP_DAYS))
            ->update(['published_at' => now(), 'bumped_at' => now(), 'updated_at' => now()]);
        if ($done === 1) {
            PageCache::bump();
        }

        return back()->with('status', __('ads.mine.bumped'));
    }

    public static function canBump(Ad $ad): bool
    {
        return feature('ads.free_bump') && $ad->isLive() && $ad->published_at !== null && $ad->published_at->lte(now()->subDays(self::BUMP_DAYS));
    }

    public static function nextBump(Ad $ad): CarbonInterface
    {
        return ($ad->published_at ?? now())->copy()->addDays(self::BUMP_DAYS);
    }

    /** Renew = same ad, new end date. No new check: the text did not change. */
    public function renew(Request $request, Ad $ad, AdForm $form): RedirectResponse
    {
        $this->own($request, $ad);
        abort_unless(feature('ads.expiry') && $this->canRenew($ad), 404);

        $ad->status = AdStatus::Live;
        $ad->closed_reason = null;
        $form->publish($ad);
        $ad->save();

        // expires_at is always set here (renew needs the "ads expire" switch), but never crash on it.
        return redirect(lroute('account.ads'))->with('status', __('ads.mine.renewed', ['date' => mdate($ad->expires_at)]));
    }

    public function destroy(Request $request, Ad $ad): RedirectResponse
    {
        $this->own($request, $ad);

        $ad->delete(); // soft delete: staff can still see it for safety reports
        Log::info('ad.deleted_by_owner', ['ad_id' => $ad->id, 'user_id' => $ad->user_id]);

        return back()->with('status', __('ads.mine.deleted'));
    }

    public static function canRenew(Ad $ad): bool
    {
        if ($ad->status === AdStatus::Expired) {
            return true;
        }

        return $ad->status === AdStatus::Live && $ad->expires_at !== null
            && $ad->expires_at->lte(now()->addDays((int) setting('ads.renew_days_before')));
    }

    private function own(Request $request, Ad $ad): void
    {
        abort_unless($ad->user_id === $this->authUser($request)->id, 404);
    }

    /**
     * @param  Builder<Ad>  $query
     * @return Builder<Ad>
     */
    private function tab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'pending' => $query->where('status', AdStatus::Pending),
            'rejected' => $query->where('status', AdStatus::Rejected),
            'ended' => $query->where(fn ($q) => $q->whereIn('status', [AdStatus::Sold, AdStatus::Expired])
                ->orWhere(fn ($q) => $q->where('status', AdStatus::Live)->whereNotNull('expires_at')->where('expires_at', '<=', now()))),
            default => $query->live(), // "live" (the only other tab; index() only passes known tabs)
        };
    }
}
