<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Search\DatabaseAdSearch;
use Illuminate\View\View;

/** Public list of a seller's ratings (switch "seller_ratings"). */
class SellerReviewsController extends Controller
{
    public function show(int $user): View
    {
        $seller = User::with('city')->find($user);
        abort_if($seller === null || $seller->isBanned() || $seller->rating_count === 0, 404);

        $reviews = $seller->reviewsReceived()->visible()->with(['buyer:id,name', 'ad:id,title'])->latest()->paginate(20);
        $liveAds = DatabaseAdSearch::publicQuery()->where('user_id', $seller->id)->count();

        return view('sellers.reviews', compact('seller', 'reviews', 'liveAds'));
    }
}
