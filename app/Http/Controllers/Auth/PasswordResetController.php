<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use App\Support\Auth\SignIn;
use App\Support\Localization\Locales;
use App\Support\Privacy\Ip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * "Forgot password" for customers (PR 105, switch "login.email").
 * - Always the same answer ("if this email has an account, we sent a link"), so nobody learns who has an account.
 * - Staff and blocked accounts never get a link (staff reset is done by a Super Admin in the admin).
 * - The link works once, for 60 minutes (Laravel's password broker keeps only a hash of it).
 * - Works for Google accounts too: they can add a password this way (they prove the email is theirs).
 * - After a reset the person is signed in, the email counts as confirmed, and "remember me" on other devices ends.
 */
class PasswordResetController extends Controller
{
    public function showForgot(): View
    {
        abort_unless(SignIn::emailOpen(), 404);

        return view('auth.forgot');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        abort_unless(SignIn::emailOpen(), 404);

        $email = SignIn::normalizeEmail((string) $request->validate(['email' => ['required', 'string', 'email', 'max:120']])['email']);
        $visitorKey = 'forgot:'.Ip::key($request);
        if (RateLimiter::tooManyAttempts($visitorKey, 5)) {
            throw ValidationException::withMessages(['email' => __('account.login.too_many', ['seconds' => RateLimiter::availableIn($visitorKey)])]);
        }
        RateLimiter::hit($visitorKey, 600);

        $user = User::where('email', $email)->first();
        if ($user && $user->role === User::CUSTOMER && ! $user->isBanned()) {
            // The broker also allows only one link a minute per account (config auth.passwords.users.throttle).
            $lang = app()->getLocale();
            PasswordBroker::broker()->sendResetLink(['email' => $email], function (User $user, string $token) use ($lang): void {
                $url = route('password.reset', ['token' => $token, 'email' => $user->email, 'lang' => $lang]);
                try {
                    Mail::to($user->email)->send(new ResetPasswordMail($user->name, $url));
                } catch (Throwable $e) {
                    Log::channel('security')->error('auth.mail_failed', ['mail' => 'ResetPasswordMail', 'error' => class_basename($e)]);
                }
            });
            Log::channel('security')->info('password.reset_requested', ['user_id' => $user->id]);
        }

        return redirect(lroute('login'))->with('status', __('account.login.reset_sent', ['email' => $email]));
    }

    public function showReset(Request $request, string $token, Locales $locales): View
    {
        abort_unless(SignIn::emailOpen(), 404);
        $this->useLinkLanguage($request, $locales);

        return view('auth.reset', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function reset(Request $request, Locales $locales): RedirectResponse
    {
        abort_unless(SignIn::emailOpen(), 404);
        $this->useLinkLanguage($request, $locales);

        $data = $request->validate([
            'token' => ['required', 'string', 'max:200'],
            'email' => ['required', 'string', 'email', 'max:120'],
            'password' => ['required', 'string', 'confirmed', 'max:100', Password::min(8)->letters()->numbers()],
        ]);
        $data['email'] = SignIn::normalizeEmail($data['email']);

        $signedIn = null;
        $status = PasswordBroker::broker()->reset(
            ['email' => $data['email'], 'password' => $data['password'], 'token' => $data['token']],
            function (User $user, string $password) use (&$signedIn): void {
                if ($user->role !== User::CUSTOMER || $user->isBanned()) {
                    return; // never for staff or blocked accounts (they get no link either)
                }
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60), // other devices' "remember me" stops working
                    'email_verified_at' => $user->email_verified_at ?? now(), // the link proved the email
                ])->save();
                $signedIn = $user;
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET || ! $signedIn instanceof User) {
            return redirect(lroute('password.request'))->with('error', __('account.login.link_bad'));
        }

        Auth::login($signedIn, remember: true);
        $request->session()->regenerate();
        $signedIn->forceFill(['last_login_at' => now()])->save();
        Log::channel('security')->info('password.reset_done', ['user_id' => $signedIn->id]);

        return redirect($signedIn->hasCompletedProfile() ? lroute('home') : lroute('welcome'))
            ->with('status', __('account.login.reset_done'));
    }

    private function useLinkLanguage(Request $request, Locales $locales): void
    {
        $lang = (string) $request->input('lang', $request->query('lang', ''));
        app()->setLocale($locales->isEnabled($lang) ? $lang : $locales->default());
    }
}
