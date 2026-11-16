<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Favourites (Phase 2, switch "favourites"): save ads for later.
 * Private: sellers never see who saved their ad.
 */
class FavouriteController extends Controller
{
    public const MAX = 500;

    public function index(Request $request): View
    {
        $ads = $this->authUser($request)->favourites()
            ->with(['category:id,name,slug,icon,parent_id', 'city:id,name,slug', 'cover'])
            ->orderByPivot('created_at', 'desc')
            ->paginate(24);

        return view('account.saved', ['ads' => $ads]);
    }

    /** Save or un-save. JSON for the heart button, a redirect when JavaScript is off. */
    public function toggle(Request $request, Ad $ad): JsonResponse|RedirectResponse
    {
        $user = $this->authUser($request);
        $saved = $user->favourites()->whereKey($ad->id)->exists();

        if ($saved) {
            $user->favourites()->detach($ad->id);
        } else {
            abort_unless($ad->isLive(), 404);
            if ($user->favourites()->count() >= self::MAX) {
                $message = __('ads.saved.full', ['max' => self::MAX]);

                return $request->expectsJson()
                    ? response()->json(['message' => $message], 422)
                    : back()->with('status', $message);
            }
            $user->favourites()->attach($ad->id);
        }

        $message = __($saved ? 'ads.saved.removed' : 'ads.saved.added');

        return $request->expectsJson()
            ? response()->json(['saved' => ! $saved, 'message' => $message])
            : back()->with('status', $message);
    }
}
