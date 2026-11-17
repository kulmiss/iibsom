<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\Report;
use App\Support\Safety\GuestReports;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * "Report this ad" for people who are not signed in (switch "ads.guest_reports").
 * Stateless: no session, no cookie, no IP stored (see GuestReports for the protection).
 * Answers are plain redirects back to the ad, so it works without JavaScript.
 */
class GuestReportController extends Controller
{
    public function store(Request $request, Ad $ad): RedirectResponse
    {
        abort_unless($ad->isLive(), 404);
        $back = fn (string $state) => redirect($ad->url().'?report='.$state.'#report');
        $token = (string) $request->input('t', '');
        GuestReports::countTry();

        // Bots: the trap field is filled, or the form came back too fast / too late / without our stamp.
        // They get the same "thanks" as people, so they learn nothing.
        if (filled($request->input('website')) || ! GuestReports::tokenOk($token)) {
            return $back('thanks');
        }

        $validator = Validator::make($request->all(), [
            'reason' => ['required', Rule::in(Report::REASONS)],
            'details' => ['nullable', 'string', 'max:500'],
            'contact' => ['nullable', 'string', 'max:120', 'regex:/^([0-9 +\-()]{6,20}|[^\s@<>]+@[^\s@<>]+\.[^\s@<>]+)$/'],
        ]);
        if ($validator->fails()) {
            return $back('error');
        }
        if (GuestReports::underAttack() && ! GuestReports::sumOk($token, $request->input('sum'))) {
            return $back('check');
        }

        $data = $validator->validated();
        $details = isset($data['details']) ? trim(strip_tags($data['details'])) ?: null : null;
        $contact = isset($data['contact']) ? trim(strip_tags($data['contact'])) ?: null : null;
        GuestReports::store($ad, $data['reason'], $details, $contact);

        return $back('thanks');
    }
}
