<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivity;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class AdminAuthTest extends AdminTestCase
{
    public function test_wrong_password_is_refused(): void
    {
        $this->staff();
        $user = User::where('role', 'super_admin')->first();

        $this->postJson('/admin/api/login', ['email' => $user->email, 'password' => 'nope'])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_customers_cannot_use_admin_login(): void
    {
        $customer = User::factory()->completed()->create(['password' => 'correct-horse-battery-9']);

        $this->postJson('/admin/api/login', ['email' => $customer->email, 'password' => 'correct-horse-battery-9'])->assertUnprocessable();
    }

    public function test_login_is_rate_limited(): void
    {
        $user = $this->staff();
        foreach (range(1, 5) as $i) {
            $this->postJson('/admin/api/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->postJson('/admin/api/login', ['email' => $user->email, 'password' => 'correct-horse-battery-9'])
            ->assertUnprocessable()->assertJsonPath('errors.email.0', fn ($m) => str_contains($m, 'Too many tries'));
    }

    public function test_password_alone_does_not_sign_in(): void
    {
        $user = $this->staff();

        $this->postJson('/admin/api/login', ['email' => $user->email, 'password' => 'correct-horse-battery-9'])
            ->assertOk()->assertJson(['next' => 'verify']);

        $this->assertGuest();
        $this->getJson('/admin/api/me')->assertUnauthorized();
    }

    public function test_first_sign_in_sets_up_2fa_and_gives_recovery_codes(): void
    {
        $user = $this->staff('moderator', with2fa: false);

        $this->postJson('/admin/api/login', ['email' => $user->email, 'password' => 'correct-horse-battery-9'])->assertJson(['next' => 'setup']);

        $setup = $this->getJson('/admin/api/2fa/setup')->assertOk()->json();
        $this->assertStringContainsString('<svg', $setup['qr_svg']);

        $this->postJson('/admin/api/2fa/confirm', ['code' => '000000'])->assertUnprocessable();

        $code = (new Google2FA)->getCurrentOtp($setup['secret']);
        $res = $this->postJson('/admin/api/2fa/confirm', ['code' => $code])->assertOk()->json();

        $this->assertCount(8, $res['recovery_codes']);
        $this->assertSame('moderator', $res['user']['role']);
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->fresh()->hasTwoFactor());
        $this->assertNotSame($setup['secret'], $user->fresh()->getRawOriginal('two_factor_secret')); // encrypted at rest
        $this->getJson('/admin/api/me')->assertOk();
    }

    public function test_verify_with_app_code(): void
    {
        $user = $this->staff();
        $this->postJson('/admin/api/login', ['email' => $user->email, 'password' => 'correct-horse-battery-9']);

        $this->postJson('/admin/api/2fa/verify', ['code' => (new Google2FA)->getCurrentOtp($user->two_factor_secret)])
            ->assertOk()->assertJsonPath('user.email', $user->email);

        $this->getJson('/admin/api/me')->assertOk()->assertJsonPath('user.role_label', 'Super Admin');
        $this->assertTrue(AdminActivity::where('action', 'staff.signed_in')->exists());
    }

    public function test_recovery_code_works_once(): void
    {
        $user = $this->staff();
        $user->forceFill(['two_factor_recovery_codes' => [Hash::make('ABCDE-12345')]])->save();

        $this->postJson('/admin/api/login', ['email' => $user->email, 'password' => 'correct-horse-battery-9']);
        $this->postJson('/admin/api/2fa/verify', ['recovery_code' => 'abcde-12345'])->assertOk();
        $this->postJson('/admin/api/logout')->assertOk();

        $this->postJson('/admin/api/login', ['email' => $user->email, 'password' => 'correct-horse-battery-9']);
        $this->postJson('/admin/api/2fa/verify', ['recovery_code' => 'ABCDE-12345'])->assertUnprocessable();
    }

    public function test_2fa_codes_are_rate_limited(): void
    {
        $user = $this->staff();
        $this->postJson('/admin/api/login', ['email' => $user->email, 'password' => 'correct-horse-battery-9']);

        foreach (range(1, 5) as $i) {
            $this->postJson('/admin/api/2fa/verify', ['code' => '000000']);
        }

        $this->postJson('/admin/api/2fa/verify', ['code' => (new Google2FA)->getCurrentOtp($user->two_factor_secret)])->assertUnprocessable();
    }

    public function test_verify_without_password_step_is_refused(): void
    {
        $user = $this->staff();

        $this->postJson('/admin/api/2fa/verify', ['code' => (new Google2FA)->getCurrentOtp($user->two_factor_secret)])->assertUnauthorized();
    }

    public function test_customer_google_session_cannot_reach_admin_api(): void
    {
        $this->actingAs(User::factory()->completed()->create());

        $this->getJson('/admin/api/me')->assertUnauthorized();
        $this->getJson('/admin/api/dashboard')->assertUnauthorized();
    }

    public function test_staff_signed_in_without_admin_2fa_flag_is_refused(): void
    {
        $this->actingAs($this->staff());

        $this->getJson('/admin/api/me')->assertUnauthorized();
    }

    public function test_idle_staff_are_signed_out(): void
    {
        $user = $this->staff();
        $this->actingAs($user)->withSession(['admin.2fa_passed' => $user->id, 'admin.last_seen' => now()->subHours(2)->getTimestamp()]);

        $this->getJson('/admin/api/me')->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_banned_staff_are_refused(): void
    {
        $user = $this->asStaff();
        $user->forceFill(['banned_at' => now()])->save();

        $this->getJson('/admin/api/me')->assertUnauthorized();
    }

    public function test_api_needs_csrf_for_writes(): void
    {
        $this->asStaff();
        // Laravel test helpers skip CSRF; make sure the route uses the web (CSRF) stack.
        $route = app('router')->getRoutes()->getByName('admin.api.appearance.update');
        $this->assertContains('web', $route->gatherMiddleware());
    }

    public function test_logout(): void
    {
        $this->asStaff();
        $this->postJson('/admin/api/logout')->assertOk();
        $this->assertGuest();
    }

    public function test_staff_command_creates_account(): void
    {
        $this->artisan('iibsom:staff', ['email' => 'Boss@Iibsom.com', '--role' => 'super_admin', '--name' => 'Boss'])
            ->expectsQuestion('Password (12+ characters, letters and numbers)', 'very-strong-pass-123')
            ->assertSuccessful();

        $user = User::where('email', 'boss@iibsom.com')->first();
        $this->assertSame('super_admin', $user->role);
        $this->assertTrue(Hash::check('very-strong-pass-123', $user->password));
    }

    public function test_staff_command_refuses_weak_password(): void
    {
        $this->artisan('iibsom:staff', ['email' => 'boss@iibsom.com'])
            ->expectsQuestion('Password (12+ characters, letters and numbers)', 'short')
            ->assertFailed();
    }

    public function test_session_endpoint_never_errors(): void
    {
        // Nobody signed in: 200 with no user (the admin app asks this before showing sign-in).
        $this->getJson('/admin/api/session')->assertOk()->assertExactJson(['user' => null])->assertHeader('Cache-Control', 'no-store, private');

        // A customer signed in on the website is not admin.
        $this->actingAs(User::factory()->completed()->create())->getJson('/admin/api/session')->assertOk()->assertJsonPath('user', null);

        // Staff who passed 2FA.
        $this->asStaff();
        $this->getJson('/admin/api/session')->assertOk()->assertJsonPath('user.role', 'super_admin');
    }

    public function test_session_endpoint_ends_idle_sessions(): void
    {
        $this->asStaff();
        $this->withSession(['admin.last_seen' => now()->subMinutes(61)->getTimestamp()]);

        $this->getJson('/admin/api/session')->assertOk()->assertJsonPath('user', null);
        $this->getJson('/admin/api/me')->assertUnauthorized(); // signed out for real
    }
}
