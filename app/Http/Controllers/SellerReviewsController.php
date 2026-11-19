<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Search\DatabaseAdSearch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Public list of a seller's ratings (switch "seller_ratings"). */
class SellerReviewsController extends Controller
{
    public function show(Request $request, int $user): View
    {
        $seller = User::with('city')->find($user);
        abort_if($seller === null || $seller->isBanned() || $seller->rating_count === 0, 404);

        $reviews = $seller->reviewsReceived()->visible()->with(['buyer:id,name', 'ad:id,title'])->latest()->paginate(20);
        $liveAds = DatabaseAdSearch::publicQuery()->where('user_id', $seller->id)->count();

        // Which of these the signed-in person marked "Helpful" (PR 85): one query for the page, none for guests.
        $viewer = $request->user();
        $liked = $viewer !== null && feature('ratings.likes') && $reviews->isNotEmpty()
            ? DB::table('review_likes')->where('user_id', $viewer->id)->whereIn('review_id', $reviews->pluck('id'))->pluck('review_id')->all()
            : [];

        return view('sellers.reviews', compact('seller', 'reviews', 'liveAds', 'liked', 'viewer'));
    }
}
