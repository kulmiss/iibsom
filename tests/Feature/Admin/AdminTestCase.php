<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

abstract class AdminTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function staff(string $role = 'super_admin', bool $with2fa = true): User
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery-9']);
        $user->forceFill(['role' => $role])->save();

        if ($with2fa) {
            $user->forceFill([
                'two_factor_secret' => (new Google2FA)->generateSecretKey(32),
                'two_factor_confirmed_at' => now(),
                'two_factor_recovery_codes' => [],
            ])->save();
        }

        return $user->fresh();
    }

    /** Signed in through the admin login (password + 2FA passed). */
    protected function asStaff(string $role = 'super_admin'): User
    {
        $user = $this->staff($role);
        $this->actingAs($user)->withSession(['admin.2fa_passed' => $user->id, 'admin.last_seen' => now()->getTimestamp()]);

        return $user;
    }
}
