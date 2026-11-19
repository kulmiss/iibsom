<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\SellerReview;
use App\Support\Cache\PageCache;
use App\Support\Safety\SpamGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Answers to seller ratings (PR 85, needs "seller_ratings"):
 * - the seller's one public reply (switch "ratings.replies"),
 * - "Helpful" likes from signed-in people (switch "ratings.likes").
 */
class ReviewResponseController extends Controller
{
    public function reply(Request $request, SellerReview $review): RedirectResponse
    {
        $user = $this->authUser($request);
        abort_if($review->hidden_at !== null, 404);
        abort_unless($review->seller_id === $user->id, 403);

        $back = lroute('seller.reviews', ['user' => $review->seller_id]).'#review-'.$review->id;
        if ($review->reply !== null) {
            return redirect($back)->withErrors(['reply' => __('ads.ratings.reply_once')]);
        }

        $data = $request->validate(['reply' => ['required', 'string', 'max:'.SellerReview::COMMENT_MAX]], [], ['reply' => __('ads.ratings.reply_label')]);
        $text = trim(strip_tags($data['reply']));
        if ($text === '') {
            return redirect($back)->withInput()->withErrors(['reply' => __('validation.required', ['attribute' => __('ads.ratings.reply_label')])]);
        }
        SpamGuard::checkAd(['reply' => $text]); // links, bad words, drugs: same as ads

        // One reply only, also when two taps arrive at the same moment.
        $saved = SellerReview::whereKey($review->id)->whereNull('reply')->whereNull('hidden_at')
            ->update(['reply' => $text, 'replied_at' => now()]);
        if ($saved === 0) {
            return redirect($back)->withErrors(['reply' => __('ads.ratings.reply_once')]);
        }
        PageCache::bump(); // the ratings page is cached for guests

        return redirect($back)->with('status', __('ads.ratings.reply_saved'));
    }

    /** Like or un-like (a second tap takes it back). Not your own rating, and not ratings about you. */
    public function like(Request $request, SellerReview $review): JsonResponse|RedirectResponse
    {
        $user = $this->authUser($request);
        abort_if($review->hidden_at !== null, 404);
        abort_if($review->seller_id === $user->id || $review->buyer_id === $user->id, 403);

        $liked = DB::transaction(function () use ($review, $user) {
            $added = DB::table('review_likes')->insertOrIgnore(['review_id' => $review->id, 'user_id' => $user->id, 'created_at' => now()]);
            if ($added > 0) {
                SellerReview::whereKey($review->id)->increment('likes_count');

                return true;
            }
            if (DB::table('review_likes')->where('review_id', $review->id)->where('user_id', $user->id)->delete() > 0) {
                SellerReview::whereKey($review->id)->where('likes_count', '>', 0)->decrement('likes_count');
            }

            return false;
        });
        // Small choice: no page-cache refresh for a like (it would empty the whole cache on every tap);
        // guests see the new number after the next refresh, signed-in people see it at once.
        $count = (int) SellerReview::whereKey($review->id)->value('likes_count');

        return $request->expectsJson()
            ? response()->json(['liked' => $liked, 'count' => $count])
            : redirect(lroute('seller.reviews', ['user' => $review->seller_id]).'#review-'.$review->id);
    }
}
