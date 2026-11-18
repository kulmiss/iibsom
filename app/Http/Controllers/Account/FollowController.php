<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Follow a seller (switch "sellers.follow"): their new ads come to your bell.
 * Private: sellers see only how many people follow them, never who.
 */
class FollowController extends Controller
{
    public const MAX = 500;

    public function index(Request $request): View
    {
        $sellers = $this->authUser($request)->following()->with('city')->orderByPivot('created_at', 'desc')->paginate(30);

        return view('account.following', ['sellers' => $sellers]);
    }

    public function toggle(Request $request, User $seller): JsonResponse|RedirectResponse
    {
        $user = $this->authUser($request);
        $following = $user->following()->whereKey($seller->id)->exists();

        if ($following) {
            $user->following()->detach($seller->id);
        } else {
            abort_if($seller->isBanned() || $seller->role !== User::CUSTOMER, 404);
            abort_if($seller->id === $user->id, 422);
            if ($user->following()->count() >= self::MAX) {
                $message = __('sellers.follow_full', ['max' => self::MAX]);

                return $request->expectsJson() ? response()->json(['message' => $message], 422) : back()->with('status', $message);
            }
            // insertOrIgnore: two quick taps at the same moment follow once, not an error.
            DB::table('follows')->insertOrIgnore(['follower_id' => $user->id, 'seller_id' => $seller->id, 'created_at' => now()]);
        }

        $message = __($following ? 'sellers.unfollowed' : 'sellers.followed', ['name' => $seller->name]);

        return $request->expectsJson()
            ? response()->json(['following' => ! $following, 'message' => $message])
            : back()->with('status', $message);
    }
}
