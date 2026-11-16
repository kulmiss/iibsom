<?php

namespace App\Http\Controllers\Account;

use App\Enums\AdStatus;
use App\Http\Controllers\Ads\PostAdController;
use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Support\Ads\AdForm;
use App\Support\Catalog;
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

        return view('account.my-ads', compact('ads', 'tab', 'counts'));
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

    /** Renew = same ad, new end date. No new check: the text did not change. */
    public function renew(Request $request, Ad $ad, AdForm $form): RedirectResponse
    {
        $this->own($request, $ad);
        abort_unless(feature('ads.expiry') && $this->canRenew($ad), 404);

        $ad->status = AdStatus::Live;
        $form->publish($ad);
        $ad->save();

        // expires_at is always set here (renew needs the "ads expire" switch), but never crash on it.
        return redirect(lroute('account.ads'))->with('status', __('ads.mine.renewed', ['date' => $ad->expires_at?->translatedFormat('j M Y') ?? '']));
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
