<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\AccountExistsMail;
use App\Mail\ConfirmEmailMail;
use App\Models\User;
use App\Support\Auth\SignIn;
use App\Support\Localization\Locales;
use App\Support\Privacy\Ip;
use App\Support\Safety\SpamGuard;
use App\Support\Watchdog\Signals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Email + password for customers (PR 105, switch "login.email").
 *
 * Safety:
 * - Nobody can find out whether an email has an account: sign-up always answers "check your email"
 *   (an existing owner gets an "you already have an account" email instead), and a wrong email or
 *   a wrong password give the same message, after the same amount of work (a dummy password check).
 * - The first sign-in needs the email link (proves the email is theirs). The link does NOT sign in:
 *   the person then signs in with their password, so a link made by somebody else is useless.
 * - Rate limits per visitor (keyed hash, cache only, never the IP itself) and per email.
 * - Staff accounts never use this: they sign in at /admin with a password and a 2FA code.
 */
class EmailAuthController extends Controller
{
    /** Hours an email-confirmation link stays valid (slow internet: people often read email later). */
    public const CONFIRM_HOURS = 24;

    public function showRegister(): View
    {
        abort_unless(SignIn::emailOpen(), 404);

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        abort_unless(SignIn::emailOpen(), 404);
        $this->honeypot($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['required', 'string', 'email:rfc', 'max:120'],
            'password' => ['required', 'string', 'confirmed', 'max:100', Password::min(8)->letters()->numbers()],
        ]);
        $email = SignIn::normalizeEmail($data['email']);

        // At most 3 sign-up emails an hour to one address: nobody can flood someone's inbox through us.
        $mailKey = 'signup-mail:'.SignIn::emailHash($email);
        if (RateLimiter::tooManyAttempts($mailKey, 3)) {
            return $this->checkEmail($email);
        }
        RateLimiter::hit($mailKey, 3600);

        $user = User::where('email', $email)->first();

        if ($user && ($user->email_verified_at !== null || $user->password === null || $user->role !== User::CUSTOMER)) {
            // Already a real account (confirmed, Google, or staff): tell the owner, sign nobody in.
            Hash::make($data['password']); // same work as a new sign-up (no timing hint)
            if ($user->role === User::CUSTOMER && ! $user->isBanned()) {
                $this->mail($user->email, new AccountExistsMail(lroute('login'), lroute('password.request')));
            }
            Log::channel('security')->info('signup.existing_email', ['user_id' => $user->id]);

            return $this->checkEmail($email);
        }

        // New, or an earlier sign-up that was never confirmed: the latest sign-up wins (older links stop working).
        $user ??= new User;
        $user->forceFill([
            'name' => trim($data['name']),
            'email' => $email,
            'password' => $data['password'],
            'role' => User::CUSTOMER,
        ])->save();

        $this->sendConfirmLink($user);
        Log::channel('security')->info('signup.email', ['user_id' => $user->id]);

        return $this->checkEmail($email);
    }

    public function login(Request $request): RedirectResponse
    {
        abort_unless(SignIn::emailOpen(), 404);
        $this->honeypot($request, 'login');

        // Errors go to their own bag ("login"), so they show under this form and not under the test box.
        $data = $request->validateWithBag('login', [
            'email' => ['required', 'string', 'email', 'max:120'],
            'password' => ['required', 'string', 'max:100'],
        ]);
        $email = SignIn::normalizeEmail($data['email']);
        $visitorKey = 'login:'.SignIn::emailHash($email).'|'.Ip::key($request);
        $emailKey = 'login-email:'.SignIn::emailHash($email); // stops slow guessing from many places

        foreach ([[$visitorKey, 5], [$emailKey, 20]] as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw ValidationException::withMessages(['email' => __('account.login.too_many', ['seconds' => RateLimiter::availableIn($key)])])->errorBag('login');
            }
        }

        $user = User::where('email', $email)->first();
        $hash = $user?->password;
        // Same work whether or not the account exists (no timing hint).
        $passwordOk = Hash::check($data['password'], $hash ?? '$2y$12$'.str_repeat('a', 53));

        if (! $user || $hash === null || ! $passwordOk) {
            RateLimiter::hit($visitorKey, 300);
            RateLimiter::hit($emailKey, 900);
            Log::channel('security')->notice('login.email_failed', ['email_hash' => SignIn::emailHash($email)]);
            Signals::hit('login_failed');

            throw ValidationException::withMessages(['email' => __('account.login.wrong')])->errorBag('login');
        }

        // From here the password was right, so these answers tell nothing to a stranger.
        RateLimiter::clear($visitorKey);

        if ($user->role !== User::CUSTOMER) {
            throw ValidationException::withMessages(['email' => __('account.login.test_staff')])->errorBag('login');
        }

        if ($user->isBanned()) {
            Log::channel('security')->notice('login.banned_user', ['user_id' => $user->id]);
            Signals::hit('banned_retry');

            return redirect(lroute('login'))->with('error', __('account.login.banned'));
        }

        if ($user->email_verified_at === null) {
            $resendKey = 'confirm-resend:'.$user->id;
            if (! RateLimiter::tooManyAttempts($resendKey, 1)) {
                RateLimiter::hit($resendKey, 120);
                $this->sendConfirmLink($user);
            }

            return $this->checkEmail($email, 'account.login.confirm_first');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        Log::channel('security')->info('login.success', ['user_id' => $user->id, 'method' => 'email']);

        if (! $user->hasCompletedProfile()) {
            return redirect(lroute('welcome'));
        }

        return redirect()->intended(lroute('home'));
    }

    /** The link from the email. Confirms the address; the person then signs in with their password. */
    public function confirm(Request $request, int $id, string $hash, Locales $locales): RedirectResponse
    {
        $lang = (string) $request->query('lang', '');
        app()->setLocale($locales->isEnabled($lang) ? $lang : $locales->default());

        $user = User::find($id);
        $valid = $request->hasValidSignature()
            && $user !== null
            && $user->role === User::CUSTOMER
            && $user->password !== null
            && hash_equals(SignIn::confirmHash($user), $hash);

        if (! $valid) {
            return redirect(lroute('login'))->with('error', __('account.login.link_bad'));
        }

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
            Log::channel('security')->info('signup.confirmed', ['user_id' => $user->id]);
        }

        return redirect(lroute('login'))
            ->with('status', __('account.login.confirmed'))
            ->withInput(['email' => $user->email]);
    }

    private function sendConfirmLink(User $user): void
    {
        $url = URL::temporarySignedRoute('auth.email.confirm', now()->addHours(self::CONFIRM_HOURS), [
            'id' => $user->id,
            'hash' => SignIn::confirmHash($user),
            'lang' => app()->getLocale(),
        ]);

        $this->mail($user->email, new ConfirmEmailMail($user->name, $url, self::CONFIRM_HOURS));
    }

    /** A mail problem must not change the answer (or show an error page). It is logged; the watchdog's daily mail check finds a broken mail set-up. */
    private function mail(string $to, Mailable $mail): void
    {
        try {
            Mail::to($to)->send($mail);
        } catch (Throwable $e) {
            Log::channel('security')->error('auth.mail_failed', ['mail' => class_basename($mail), 'error' => class_basename($e)]);
        }
    }

    /** The same answer for every sign-up, so nobody learns which emails have accounts. */
    private function checkEmail(string $email, string $message = 'account.login.check_email'): RedirectResponse
    {
        return redirect(lroute('login'))
            ->with('status', __($message, ['email' => $email]))
            ->withInput(['email' => $email]);
    }

    private function honeypot(Request $request, string $bag = 'default'): void
    {
        if (feature('safety.honeypot') && filled($request->input(SpamGuard::HONEYPOT))) {
            Log::channel('security')->warning('spam.honeypot', ['route' => $request->route()?->getName()]);
            throw ValidationException::withMessages(['email' => __('ads.safety.bot')])->errorBag($bag);
        }
    }
}
