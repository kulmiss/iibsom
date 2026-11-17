<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Test sign-in for the owner's OWN computer (switch "login.test_accounts"): customers normally sign in
 * only with Google, so without Google keys nobody could sign up, post or chat while testing.
 * Works ONLY when APP_ENV=local. On the live site (production) and anywhere else it answers 404.
 * It does what the Google sign-in does: find or make the customer, sign in, then "Almost done" if new.
 * Never for staff accounts (they sign in to /admin with a password and 2FA).
 */
class TestSignInController extends Controller
{
    public static function available(): bool
    {
        return app()->environment('local') && feature('login.test_accounts');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(self::available(), 404);

        $email = Str::lower(trim((string) $request->validate(['email' => ['required', 'email:rfc', 'max:120']])['email']));
        $user = User::where('email', $email)->first();
        if ($user && $user->role !== 'customer') {
            throw ValidationException::withMessages(['email' => __('account.login.test_staff')]);
        }
        if ($user && $user->isBanned()) {
            return redirect(lroute('login'))->with('error', __('account.login.banned'));
        }

        if (! $user) {
            $user = new User(['name' => Str::limit(Str::headline(Str::before($email, '@')), 80, '')]);
            $user->email = $email;
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        Log::channel('security')->info('login.test_sign_in', ['user_id' => $user->id]);

        return $user->hasCompletedProfile() ? redirect()->intended(lroute('home')) : redirect(lroute('welcome'));
    }
}
