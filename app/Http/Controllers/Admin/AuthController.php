<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AdminAuthenticate;
use App\Models\User;
use App\Support\Admin\Activity;
use App\Support\Privacy\Ip;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

/**
 * Staff sign-in: 1) email + password  2) 2FA code (set up on first sign-in).
 * The user is only really signed in after step 2.
 */
class AuthController extends Controller
{
    public function __construct(private readonly Google2FA $google2fa) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = 'admin-login:'.hash('sha256', Str::lower($data['email'])).'|'.Ip::key($request);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many tries. Wait '.RateLimiter::availableIn($key).' seconds.']);
        }

        $user = User::where('email', Str::lower($data['email']))->first();
        $valid = $user && $user->password && Hash::check($data['password'], $user->password) && $user->isStaff() && ! $user->isBanned();

        if (! $valid) {
            RateLimiter::hit($key, 300);
            Log::channel('security')->warning('admin.login_failed', ['email_hash' => hash('sha256', Str::lower($data['email']))]);
            throw ValidationException::withMessages(['email' => 'Email or password is wrong.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put('admin.login_user', $user->id);
        $request->session()->forget(['admin.2fa_passed', 'admin.2fa_setup_secret']);

        return response()->json(['next' => $user->hasTwoFactor() ? 'verify' : 'setup']);
    }

    /** First sign-in: show a QR code for the authenticator app. */
    public function setup(Request $request): JsonResponse
    {
        $user = $this->pendingUser($request);
        abort_if($user->hasTwoFactor(), 409, '2FA is already set up.');

        $secret = $request->session()->get('admin.2fa_setup_secret') ?? $this->google2fa->generateSecretKey(32);
        $request->session()->put('admin.2fa_setup_secret', $secret);

        $url = $this->google2fa->getQRCodeUrl(config('app.name').' Admin', $user->email, $secret);
        $svg = (new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd)))->writeString($url);

        return response()->json(['qr_svg' => $svg, 'secret' => $secret]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $user = $this->pendingUser($request);
        $secret = $request->session()->get('admin.2fa_setup_secret');
        $code = $request->validate(['code' => ['required', 'digits:6']])['code'];
        $this->throttleCodes($request, $user);

        if (! $secret || ! $this->google2fa->verifyKey($secret, $code, 1)) {
            throw ValidationException::withMessages(['code' => 'That code is not right. Try the newest code in your app.']);
        }

        $codes = collect(range(1, 8))->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))->all();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), $codes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->finishLogin($request, $user);
        Activity::log('staff.2fa_enabled', "{$user->name} set up 2FA", $user);

        return response()->json(['recovery_codes' => $codes, 'user' => $this->userPayload($user)]);
    }

    public function verify(Request $request): JsonResponse
    {
        $user = $this->pendingUser($request);
        abort_unless($user->hasTwoFactor(), 409, 'Set up 2FA first.');
        $data = $request->validate(['code' => ['nullable', 'digits:6'], 'recovery_code' => ['nullable', 'string', 'max:20']]);
        $this->throttleCodes($request, $user);

        $ok = false;
        $secret = $user->two_factor_secret;
        if (! empty($data['code']) && $secret !== null) {
            $ok = $this->google2fa->verifyKey($secret, $data['code'], 1);
        } elseif (! empty($data['recovery_code'])) {
            $ok = $this->useRecoveryCode($user, Str::upper(trim($data['recovery_code'])));
        }

        if (! $ok) {
            Log::channel('security')->warning('admin.2fa_failed', ['user_id' => $user->id]);
            throw ValidationException::withMessages(['code' => 'That code is not right.']);
        }

        $this->finishLogin($request, $user);

        return response()->json(['user' => $this->userPayload($user)]);
    }

    /** Who is signed in, without an error when nobody is (the admin app asks this first). */
    public function session(Request $request): JsonResponse
    {
        return response()->json(['user' => AdminAuthenticate::check($request) ? $this->userPayload($this->authUser($request)) : null])
            ->header('Cache-Control', 'no-store');
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($this->authUser($request))]);
    }

    public function logout(Request $request): JsonResponse
    {
        Activity::log('staff.signed_out', $this->authUser($request)->name.' signed out', $this->authUser($request));
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    private function pendingUser(Request $request): User
    {
        $id = $request->session()->get('admin.login_user');
        $user = is_int($id) || is_string($id) ? User::find($id) : null;
        abort_unless($user instanceof User && $user->isStaff() && ! $user->isBanned(), 401, 'Please sign in again.');

        return $user;
    }

    private function throttleCodes(Request $request, User $user): void
    {
        $key = 'admin-2fa:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Too many tries. Wait '.RateLimiter::availableIn($key).' seconds.']);
        }
        RateLimiter::hit($key, 300);
    }

    private function useRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        foreach ($codes as $i => $hash) {
            if (Hash::check($code, $hash)) {
                unset($codes[$i]); // each code works once
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();
                Log::channel('security')->notice('admin.recovery_code_used', ['user_id' => $user->id, 'left' => count($codes)]);

                return true;
            }
        }

        return false;
    }

    private function finishLogin(Request $request, User $user): void
    {
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget(['admin.login_user', 'admin.2fa_setup_secret']);
        $request->session()->put('admin.2fa_passed', $user->id);
        $request->session()->put('admin.last_seen', now()->getTimestamp());
        RateLimiter::clear('admin-2fa:'.$user->id);
        $user->forceFill(['last_login_at' => now()])->save();
        Activity::log('staff.signed_in', $user->name.' signed in', $user);
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'role_label' => config("admin.roles.{$user->role}.label"),
            'permissions' => $user->permissions(),
            'brand_color' => setting('admin.brand_color'),
            'demo_data' => feature('site.demo_data'),
            'guide' => feature('help.admin_guide'),
            'verified_badge' => feature('verified_badge'),
            'seller_ratings' => feature('seller_ratings'),
            'diaspora' => feature('diaspora'),
            'shops' => feature('shops'),
            'boosts' => feature('boosts'),
            'contact_form' => feature('site.contact_form'),
            'home_page' => feature('admin.home_page'),
            'setup_needed' => feature('admin.setup_wizard') && $user->hasPermission('settings.manage') && ! SetupController::completed(),
        ];
    }
}
