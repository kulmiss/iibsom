<?php

namespace App\Support\Watchdog;

use App\Models\User;
use App\Support\Admin\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Remembers the browsers staff sign in from (PR 95). Each browser gets a random id in a long-lived cookie;
 * only its hash is stored, with a label like "Chrome on Windows". A sign-in from a browser this staff member
 * never used is written to the activity log ("staff.new_device"); the Watchdog tells the owner.
 */
class StaffDevices
{
    public const COOKIE = 'iibsom_staff_device';

    public function signedIn(Request $request, User $user): void
    {
        $id = $request->cookie(self::COOKIE);
        $id = is_string($id) ? $id : '';
        if (! preg_match('/^[A-Za-z0-9]{40}$/', $id)) {
            $id = Str::random(40);
        }
        Cookie::queue(Cookie::make(self::COOKIE, $id, 60 * 24 * 365, '/admin', null, null, true, false, 'lax'));

        $hash = hash('sha256', $id);
        $label = self::label((string) $request->userAgent());
        $known = DB::table('staff_devices')->where('user_id', $user->id)->where('device_hash', $hash)->exists();

        if ($known) {
            DB::table('staff_devices')->where('user_id', $user->id)->where('device_hash', $hash)->update(['last_seen_at' => now(), 'label' => $label]);

            return;
        }
        $first = ! DB::table('staff_devices')->where('user_id', $user->id)->exists();
        DB::table('staff_devices')->insertOrIgnore(['user_id' => $user->id, 'device_hash' => $hash, 'label' => $label, 'first_seen_at' => now(), 'last_seen_at' => now()]);
        Activity::log('staff.new_device', "{$user->name} signed in from a new browser ({$label})", $user, ['label' => $label, 'first' => $first]);
    }

    /** "Chrome on Windows" — browser and system only, nothing that tells who or where. */
    public static function label(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'A browser',
        };
        $system = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') => 'iPhone / iPad',
            str_contains($agent, 'Mac OS') => 'Mac',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'an unknown system',
        };

        return "{$browser} on {$system}";
    }
}
