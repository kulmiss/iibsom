<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Localization\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as GoogleUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * "Continue with Google". Done safely:
 * - state check (Socialite, stops login CSRF)
 * - only Google accounts with a VERIFIED email
 * - banned users cannot sign in
 * - new session id after sign-in (stops session fixation)
 */
class GoogleController extends Controller
{
    public function redirect(Request $request, Locales $locales): RedirectResponse
    {
        // Remember the visitor's language so we come back to the right one.
        $lang = (string) $request->query('lang', '');
        $request->session()->put('login_locale', $locales->isEnabled($lang) ? $lang : $locales->default());

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        app()->setLocale($request->session()->get('login_locale', app()->getLocale()));
        $loginUrl = lroute('login');

        if ($request->has('error')) { // user pressed "Cancel" on Google
            return redirect($loginUrl)->with('error', __('account.login.cancelled'));
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            Log::channel('security')->warning('google.callback_failed', ['error' => class_basename($e)]);

            return redirect($loginUrl)->with('error', __('account.login.failed'));
        }

        if (! $this->emailIsVerified($google)) {
            Log::channel('security')->notice('google.unverified_email');

            return redirect($loginUrl)->with('error', __('account.login.unverified'));
        }

        $user = $this->findOrCreate($google);

        if ($user->isBanned()) {
            Log::channel('security')->notice('login.banned_user', ['user_id' => $user->id]);

            return redirect($loginUrl)->with('error', __('account.login.banned'));
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();
        Log::channel('security')->info('login.success', ['user_id' => $user->id]);

        if (! $user->hasCompletedProfile()) {
            return redirect(lroute('welcome'));
        }

        return redirect()->intended(lroute('home'));
    }

    private function emailIsVerified(GoogleUser $google): bool
    {
        $raw = method_exists($google, 'getRaw') ? $google->getRaw() : ($google->user ?? []);
        $verified = $raw['email_verified'] ?? $raw['verified_email'] ?? false;

        return filter_var($verified, FILTER_VALIDATE_BOOLEAN) && filled($google->getEmail());
    }

    private function findOrCreate(GoogleUser $google): User
    {
        $email = Str::lower($google->getEmail());

        $user = User::where('google_id', $google->getId())->first()
            ?? User::where('email', $email)->first();

        if (! $user) {
            $user = new User(['name' => Str::limit(trim($google->getName() ?: Str::before($email, '@')), 80, '')]);
            $user->email = $email;
        }

        $user->forceFill([
            'google_id' => $google->getId(),
            'avatar_url' => $google->getAvatar(),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        return $user;
    }
}
