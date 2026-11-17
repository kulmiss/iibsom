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
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
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
    public function redirect(Request $request, Locales $locales): SymfonyRedirect
    {
        // Remember the visitor's language so we come back to the right one.
        $lang = (string) $request->query('lang', '');
        $request->session()->put('login_locale', $locales->isEnabled($lang) ? $lang : $locales->default());

        // No Google keys yet (for example on your own computer): explain, instead of an error page at Google.
        if (blank(config('services.google.client_id')) || blank(config('services.google.client_secret'))) {
            return redirect(lroute('login'))->with('error', __('account.login.not_set_up'));
        }

        // Google's default scopes are already only: openid, profile, email.
        $provider = Socialite::driver('google');
        if ($provider instanceof AbstractProvider) {
            $provider->with(['prompt' => 'select_account']); // let people pick the right account
        }

        return $provider->redirect();
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

        $email = $google->getEmail();
        if ($email === null) { // emailIsVerified() already checks this; never continue without one
            return redirect($loginUrl)->with('error', __('account.login.unverified'));
        }
        $user = $this->findOrCreate($google, $email);

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

    private function findOrCreate(GoogleUser $google, string $email): User
    {
        $email = Str::lower($email);

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
