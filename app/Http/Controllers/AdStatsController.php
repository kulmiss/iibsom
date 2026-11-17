<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Support\Ads\AdStats;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AdStatsController extends Controller
{
    /**
     * Beacon from the ad page (view, WhatsApp or call tap). Stateless (no session,
     * cookie or CSRF token): guests have none on cached pages, and the worst a fake
     * request can do is add 1 to a counter only the seller sees (rate-limited).
     * The seller's own ad page has no beacon, so their visits are not counted.
     */
    public function event(Request $request, Ad $ad): Response
    {
        // No session here, so no validation redirect: a bad event is simply refused.
        $event = $request->input('e');
        if (! in_array($event, ['views', 'whatsapp', 'call'], true)) {
            return response()->noContent(422);
        }
        AdStats::record($ad, $event, $request);

        return response()->noContent()->header('Cache-Control', 'no-store');
    }

    /** The seller's stats page for one ad. */
    public function show(Request $request, Ad $ad): View
    {
        abort_unless($ad->user_id === $this->authUser($request)->id, 404);

        return view('account.ad-stats', [
            'ad' => $ad,
            'totals' => AdStats::totals([$ad->id])[$ad->id] ?? ['views' => 0, 'phone' => 0, 'whatsapp' => 0, 'call' => 0],
            'days' => AdStats::daily($ad),
        ]);
    }
}
