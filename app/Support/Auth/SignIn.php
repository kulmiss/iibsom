<?php

namespace App\Support\Auth;

use App\Http\Controllers\Auth\TestSignInController;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Which ways of signing in are open right now (PR 105).
 * - Google: switch "login.google" ON **and** the Google keys are in .env. Without keys the button hides itself.
 * - Email + password: switch "login.email".
 * - Test sign-in: the owner's own computer only.
 */
class SignIn
{
    public static function googleReady(): bool
    {
        return feature('login.google')
            && filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    public static function emailOpen(): bool
    {
        return feature('login.email');
    }

    /** Is there any way to sign in? (Shows the "Sign in" links, the Post button for guests, etc.) */
    public static function open(): bool
    {
        return self::emailOpen() || self::googleReady() || TestSignInController::available();
    }

    public static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    /** Short, non-reversible label for logs and rate-limit keys (never the email itself). */
    public static function emailHash(string $email): string
    {
        return hash('sha256', self::normalizeEmail($email));
    }

    /**
     * Ties an email-confirmation link to the email AND the current password. When someone signs up again
     * with the same (still unconfirmed) email, the password changes and older links stop working.
     */
    public static function confirmHash(User $user): string
    {
        return substr(hash_hmac('sha256', $user->email.'|'.$user->getAuthPassword(), (string) config('app.key')), 0, 40);
    }
}
