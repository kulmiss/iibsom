<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Search\DatabaseAdSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Public seller page (switch "sellers.page"): every seller's live ads in one place.
 * Only what the ad page already shows (name, city, member since, badges). Not in Google (noindex),
 * because most sellers are private people; business shops have their own indexed page.
 */
class SellerPageController extends Controller
{
    public function show(int $user): View|RedirectResponse
    {
        $seller = User::with('city')->find($user);
        abort_if($seller === null || $seller->isBanned() || $seller->role !== User::CUSTOMER || ! $seller->profile_completed_at, 404);
        abort_if($seller->is_demo && ! feature('site.demo_data'), 404);

        if (feature('shops') && $seller->shop?->isActive()) {
            return redirect($seller->shop->url(), 301); // a business: its shop page has everything
        }

        $ads = DatabaseAdSearch::publicQuery()->where('ads.user_id', $seller->id)
            ->with(['category:id,slug,icon,parent_id', 'city:id,name,slug', 'cover'])->withPhotoCount()
            ->latest('published_at')->paginate(24);
        abort_if($ads->total() === 0 && $ads->currentPage() > 1, 404);

        $user = auth()->user();
        $following = $user !== null && feature('sellers.follow') && $user->following()->whereKey($seller->id)->exists();
        $followers = feature('sellers.follow') ? (int) DB::table('follows')->where('seller_id', $seller->id)->count() : 0;

        return view('sellers.show', compact('seller', 'ads', 'following', 'followers'));
    }
}
