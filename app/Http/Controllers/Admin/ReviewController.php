<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerReview;
use App\Support\Admin\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Staff hide (or show again) seller ratings that break the rules. */
class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $hidden = $request->query('show') === 'hidden';
        $stars = (int) $request->query('stars', '0');
        $page = SellerReview::query()->with(['seller:id,name', 'buyer:id,name', 'ad:id,title'])
            ->when($hidden, fn ($q) => $q->whereNotNull('hidden_at'), fn ($q) => $q->whereNull('hidden_at'))
            ->when($stars >= 1 && $stars <= 5, fn ($q) => $q->where('stars', $stars))
            ->latest('id')->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (SellerReview $r) => [
                'id' => $r->id,
                'stars' => $r->stars,
                'comment' => $r->comment,
                'reply' => $r->reply,
                'replied_at' => $r->replied_at?->toIso8601String(),
                'likes_count' => (int) $r->likes_count,
                'created_at' => $r->created_at?->toIso8601String(),
                'hidden_at' => $r->hidden_at?->toIso8601String(),
                'hidden_reason' => $r->hidden_reason,
                'seller' => ['id' => $r->seller->id, 'name' => $r->seller->name],
                'buyer' => ['id' => $r->buyer->id, 'name' => $r->buyer->name],
                'ad' => $r->ad ? ['id' => $r->ad->id, 'title' => $r->ad->title] : null,
            ])->values(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function hide(Request $request, SellerReview $review): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        $review->forceFill(['hidden_at' => now(), 'hidden_by' => $this->authUser($request)->id, 'hidden_reason' => $data['reason']])->save();
        SellerReview::recount($review->seller_id);
        Activity::log('review.hidden', "Hid a {$review->stars}-star rating of {$review->seller->name}: {$data['reason']}", $review->seller);

        return response()->json(['hidden' => true]);
    }

    public function unhide(SellerReview $review): JsonResponse
    {
        $review->forceFill(['hidden_at' => null, 'hidden_by' => null, 'hidden_reason' => null])->save();
        SellerReview::recount($review->seller_id);
        Activity::log('review.shown', "Showed again a {$review->stars}-star rating of {$review->seller->name}", $review->seller);

        return response()->json(['hidden' => false]);
    }
}
