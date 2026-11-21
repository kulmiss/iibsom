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
 * Test sign-in for the owner's OWN computer (switch "login.test_accounts"), so testing needs no Google keys
 * and no real email. Works ONLY when APP_ENV=local on a local address; on the live site the route does not exist (404).
 * It does what the Google sign-in does: find or make the customer, sign in, then "Almost done" if new.
 * Never for staff accounts (they sign in to /admin with a password and 2FA).
 */
class TestSignInController extends Controller
{
    /**
     * Is the route made at all? Never when APP_ENV=production: there the address does not exist (404),
     * also in a route cache built there. ("testing" only so the automatic tests can check the box.)
     */
    public static function routeAllowed(): bool
    {
        return ! app()->isProduction() && app()->environment(['local', 'testing']);
    }

    /**
     * Does the box work right now? Every check must pass, so one wrong value is not enough to open it (PR 105):
     * APP_ENV is exactly "local", it is not production, the address is the owner's own computer
     * (localhost, 127.0.0.1, ::1, *.test, *.localhost — never iibsom.com), and the switch is ON.
     */
    public static function available(): bool
    {
        return app()->environment('local')
            && ! app()->isProduction()
            && self::isOwnComputer(request()->getHost())
            && feature('login.test_accounts');
    }

    public static function isOwnComputer(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.localhost');
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
