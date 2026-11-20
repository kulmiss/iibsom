<?php

namespace App\Http\Controllers;

use App\Models\ScamAppeal;
use App\Models\ScamNumber;
use App\Support\Phone\SomaliPhone;
use App\Support\Privacy\Ip;
use App\Support\Safety\ScamNumbers;
use App\Support\Safety\SpamGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "Check a phone number" and "ask for a review" (PR 109, switch "safety.scam_numbers").
 * Only says whether staff marked the number "Reported for scams". Never a name, never who reported it.
 * Nothing about the visitor is stored (rate limits use a keyed hash in the cache).
 */
class ScamCheckController extends Controller
{
    public function show(Request $request): View
    {
        $raw = trim((string) $request->query('phone', ''));
        $phone = $raw === '' ? null : SomaliPhone::normalize(mb_substr($raw, 0, 30));

        if ($raw !== '') {
            $key = 'scam-check:'.Ip::key($request);
            abort_if(RateLimiter::tooManyAttempts($key, 30), 429);
            RateLimiter::hit($key, 60);
        }

        return view('safety.check-number', [
            'raw' => $raw,
            'phone' => $phone,
            'listed' => $phone !== null && ScamNumbers::listed($phone),
            'waiting' => $phone !== null && ScamAppeal::where('status', 'waiting')->whereHas('number', fn ($q) => $q->where('phone', $phone))->exists(),
        ]);
    }

    public function appeal(Request $request): RedirectResponse
    {
        if (feature('safety.honeypot') && filled($request->input(SpamGuard::HONEYPOT))) {
            Log::channel('security')->warning('spam.honeypot', ['route' => 'scam.appeal']);
            throw ValidationException::withMessages(['message' => __('ads.safety.bot')]);
        }

        $key = 'scam-appeal:'.Ip::key($request);
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['message' => __('scam.appeal_too_many')]);
        }

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'message' => ['required', 'string', 'min:10', 'max:1000'],
            'contact' => ['nullable', 'string', 'max:120'],
        ], [], ['message' => __('scam.appeal_message'), 'contact' => __('scam.appeal_contact')]);

        $phone = SomaliPhone::normalize($data['phone']);
        $number = $phone === null ? null : ScamNumber::active()->where('phone', $phone)->first();
        if ($number === null) {
            return redirect(lroute('scam.check', ['phone' => $data['phone']]));
        }
        RateLimiter::hit($key, 3600);

        if (! $number->appeals()->where('status', 'waiting')->exists()) {
            $appeal = new ScamAppeal([
                'message' => trim(strip_tags($data['message'])),
                'contact' => filled($data['contact'] ?? null) ? trim(strip_tags((string) $data['contact'])) : null,
            ]);
            $number->appeals()->save($appeal);
            Log::channel('security')->info('scam_number.appeal', ['scam_number_id' => $number->id]);
        }

        return redirect(lroute('scam.check', ['phone' => $phone]))->with('status', __('scam.appeal_sent'));
    }
}
