<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Phone\SomaliPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Confirm my number" (PR 108, switch "sellers.phone_confirmed"). No SMS: the seller gets a short code and sends
 * it to the iibsom WhatsApp number; a staff member matches the code and ticks "Phone confirmed" in the admin.
 * The seller can never tick it themselves.
 */
class PhoneConfirmController extends Controller
{
    /** A code is good for this many days; asking again gives the same code while it is fresh. */
    public const CODE_DAYS = 7;

    /** No 0/O or 1/I/L: easy to read out and type. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function show(Request $request): View
    {
        $user = $this->authUser($request);

        return view('account.phone', [
            'user' => $user,
            'code' => $this->freshCode($user),
            'whatsapp' => self::whatsappDigits(),
        ]);
    }

    public function code(Request $request): RedirectResponse
    {
        $user = $this->authUser($request);
        if ($user->phone_confirmed_at !== null || blank($user->phone)) {
            return redirect(lroute('account.phone'));
        }

        if ($this->freshCode($user) === null) {
            do {
                $code = 'IIB-'.collect(range(1, 5))->map(fn () => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)])->implode('');
            } while (User::where('phone_confirm_code', $code)->exists());
            $user->forceFill(['phone_confirm_code' => $code, 'phone_confirm_code_at' => now()])->save();
        }

        return redirect(lroute('account.phone'))->with('status', __('account.phone.code_ready'));
    }

    private function freshCode(User $user): ?string
    {
        return $user->phone_confirm_code !== null && $user->phone_confirm_code_at?->gt(now()->subDays(self::CODE_DAYS))
            ? $user->phone_confirm_code
            : null;
    }

    /** The iibsom WhatsApp number as digits for a wa.me link (e.g. 252612345678), or null when not set. */
    public static function whatsappDigits(): ?string
    {
        $raw = trim((string) setting('contact.confirm_whatsapp'));
        if ($raw === '') {
            return null;
        }
        $digits = ltrim((string) preg_replace('/\D+/', '', SomaliPhone::normalize($raw) ?? $raw), '0');

        return strlen($digits) >= 8 ? $digits : null;
    }
}
