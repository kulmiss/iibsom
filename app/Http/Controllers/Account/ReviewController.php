<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\SellerReview;
use App\Support\Safety\SpamGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Rate the seller from the chat (switch "seller_ratings"). Sending again changes your rating. */
class ReviewController extends Controller
{
    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        $user = $this->authUser($request);
        abort_unless($conversation->has($user), 404);
        abort_unless(SellerReview::canRate($conversation, $user), 403);

        $data = $request->validate([
            'stars' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:'.SellerReview::COMMENT_MAX],
        ], [], ['stars' => __('chat.review.stars'), 'comment' => __('chat.review.comment')]);
        $comment = trim(strip_tags((string) ($data['comment'] ?? ''))) ?: null;
        SpamGuard::checkAd(['comment' => $comment]);

        DB::transaction(function () use ($conversation, $user, $data, $comment) {
            $review = SellerReview::firstOrNew(['conversation_id' => $conversation->id]);
            $review->forceFill([
                'conversation_id' => $conversation->id,
                'seller_id' => $conversation->seller_id,
                'buyer_id' => $user->id,
                'ad_id' => $conversation->ad_id,
            ])->fill(['stars' => (int) $data['stars'], 'comment' => $comment]); // staff hiding stays as it was
            $review->save();
            SellerReview::recount($conversation->seller_id);
        });

        return redirect(lroute('chat.show', ['conversation' => $conversation->id]))->with('status', __('chat.review.thanks'));
    }
}
