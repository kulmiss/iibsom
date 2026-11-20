<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\EmailAuthController;
use App\Mail\AccountExistsMail;
use App\Mail\ConfirmEmailMail;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Privacy\Ip;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/** PR 105: email + password sign-in for customers. */
class EmailSignInTest extends TestCase
{
    use RefreshDatabase;

    private const PASS = 'goodPass123';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['services.google.client_id' => null, 'services.google.client_secret' => null]);
        Mail::fake();
    }

    private function register(array $data = []): TestResponse
    {
        return $this->post('/register', $data + ['name' => 'Asha Ali', 'email' => 'asha@example.com', 'password' => self::PASS, 'password_confirmation' => self::PASS]);
    }

    private function confirmUrl(string $email = 'asha@example.com'): string
    {
        $url = null;
        Mail::assertSent(ConfirmEmailMail::class, function (ConfirmEmailMail $mail) use ($email, &$url) {
            if ($mail->hasTo($email)) {
                $url = $mail->url;
            }

            return true;
        });
        $this->assertNotNull($url);

        return (string) $url;
    }

    private function customer(array $attributes = []): User
    {
        return User::factory()->completed()->create($attributes + ['email' => 'asha@example.com', 'password' => Hash::make(self::PASS), 'email_verified_at' => now()]);
    }

    // ---- The sign-in page ----

    public function test_login_page_has_the_email_form_and_hides_google_without_keys(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('data-email-login', false)->assertSee('Forgot password?')->assertSee('Create an account')
            ->assertDontSee('data-google-button', false)->assertDontSee('Continue with Google');
        $this->get('/register')->assertOk()->assertDontSee('data-google-button', false);
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->get('/so/login')->assertOk()->assertSee('Ma ilowday furaha sirta ah?');
    }

    public function test_google_button_shows_only_with_keys_and_its_switch(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
        $this->get('/login')->assertOk()->assertSee('data-google-button', false)->assertSee('data-email-login', false);

        app(FeatureManager::class)->set('login.google', false);
        $this->get('/login')->assertOk()->assertDontSee('data-google-button', false)->assertSee('data-email-login', false);
        $this->get('/auth/google')->assertNotFound();
    }

    public function test_email_switch_off_closes_every_email_page(): void
    {
        app(FeatureManager::class)->set('login.email', false);
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);

        $this->get('/login')->assertOk()->assertDontSee('data-email-login', false)->assertSee('data-google-button', false);
        $this->get('/register')->assertNotFound();
        $this->register()->assertNotFound();
        $this->post('/login/email', ['email' => 'a@example.com', 'password' => 'x'])->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', ['email' => 'a@example.com'])->assertNotFound();
        $this->get('/auth/reset-password/abc')->assertNotFound();
    }

    public function test_no_way_to_sign_in_hides_the_page_and_the_links(): void
    {
        app(FeatureManager::class)->set('login.email', false);
        app(FeatureManager::class)->set('login.google', false);

        $this->get('/login')->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('/login"', false);
    }

    public function test_sign_in_links_show_with_email_only(): void
    {
        app(FeatureManager::class)->set('login.google', false);
        $this->get('/')->assertOk()->assertSee(url('/login'), false);
    }

    // ---- Sign up ----

    public function test_sign_up_makes_an_unconfirmed_account_and_sends_a_link_without_signing_in(): void
    {
        $this->register(['email' => ' Asha@Example.COM '])
            ->assertRedirect(url('/login'))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Check your email (asha@example.com)'));

        $this->assertGuest();
        $user = User::where('email', 'asha@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertSame('customer', $user->role);
        $this->assertSame('Asha Ali', $user->name);
        $this->assertTrue(Hash::check(self::PASS, (string) $user->password));
        $this->assertStringContainsString('/auth/confirm-email/'.$user->id.'/', $this->confirmUrl());
        Mail::assertNotSent(AccountExistsMail::class);
    }

    public function test_sign_up_with_a_used_email_gives_the_same_answer_and_changes_nothing(): void
    {
        $this->register(['email' => 'new@example.com']);
        $newAnswer = session('status');

        $owner = $this->customer();
        $hash = $owner->password;
        $this->register(['password' => 'otherPass999', 'password_confirmation' => 'otherPass999', 'name' => 'Hacker'])
            ->assertRedirect(url('/login'));

        // Same words (only the email differs), no sign-in, the owner's account is untouched.
        $this->assertSame(str_replace('new@example.com', 'asha@example.com', (string) $newAnswer), session('status'));
        $this->assertGuest();
        $owner->refresh();
        $this->assertSame($hash, $owner->password);
        $this->assertNotSame('Hacker', $owner->name);
        Mail::assertSent(AccountExistsMail::class, fn ($m) => $m->hasTo('asha@example.com'));
        Mail::assertNotSent(ConfirmEmailMail::class, fn ($m) => $m->hasTo('asha@example.com'));
    }

    public function test_google_account_and_staff_emails_never_get_a_password_by_sign_up(): void
    {
        $google = User::factory()->completed()->create(['email' => 'g@example.com', 'password' => null, 'google_id' => '123', 'email_verified_at' => now()]);
        $staff = User::factory()->create(['email' => 'mod@iibsom.test', 'role' => 'moderator', 'email_verified_at' => null]);

        $this->register(['email' => 'g@example.com'])->assertRedirect(url('/login'));
        $this->register(['email' => 'mod@iibsom.test'])->assertRedirect(url('/login'));

        $this->assertNull($google->fresh()->password);
        $this->assertSame('moderator', $staff->fresh()->role);
        $this->assertSame($staff->password, $staff->fresh()->password);
        Mail::assertSent(AccountExistsMail::class, fn ($m) => $m->hasTo('g@example.com'));
        Mail::assertNotSent(AccountExistsMail::class, fn ($m) => $m->hasTo('mod@iibsom.test')); // staff: nothing at all
    }

    public function test_signing_up_again_before_confirming_makes_older_links_useless(): void
    {
        $this->register();
        $first = $this->confirmUrl();
        Mail::fake();
        $this->register(['password' => 'secondPass2', 'password_confirmation' => 'secondPass2']);
        $second = $this->confirmUrl();

        $this->get($first)->assertRedirect(url('/login'))->assertSessionHas('error', __('account.login.link_bad'));
        $this->assertNull(User::where('email', 'asha@example.com')->value('email_verified_at'));

        $this->get($second)->assertRedirect(url('/login'))->assertSessionHas('status', __('account.login.confirmed'));
        $this->assertNotNull(User::where('email', 'asha@example.com')->value('email_verified_at'));
    }

    public function test_sign_up_checks_the_fields(): void
    {
        $this->register(['password' => 'short1', 'password_confirmation' => 'short1'])->assertSessionHasErrors('password');
        $this->register(['password' => 'onlyletters', 'password_confirmation' => 'onlyletters'])->assertSessionHasErrors('password');
        $this->register(['password_confirmation' => 'different1'])->assertSessionHasErrors('password');
        $this->register(['email' => 'not-an-email'])->assertSessionHasErrors('email');
        $this->register(['name' => ''])->assertSessionHasErrors('name');
        $this->register(['name' => str_repeat('a', 81)])->assertSessionHasErrors('name');
        $this->assertSame(0, User::where('email', 'asha@example.com')->count());
    }

    public function test_robots_filling_the_hidden_field_are_stopped(): void
    {
        $this->register(['website' => 'http://spam.example'])->assertSessionHasErrors('email');
        $this->assertSame(0, User::where('email', 'asha@example.com')->count());
        Mail::assertNothingSent();
    }

    public function test_at_most_three_sign_up_emails_an_hour_to_one_address(): void
    {
        foreach (range(1, 5) as $i) {
            $this->register(['password' => "pass{$i}word9", 'password_confirmation' => "pass{$i}word9"])->assertRedirect(url('/login'));
        }
        Mail::assertSent(ConfirmEmailMail::class, 3);
    }

    public function test_a_mail_problem_still_gives_the_normal_answer(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('smtp down'));

        $this->register()->assertRedirect(url('/login'))->assertSessionHas('status');
        $this->assertSame(1, User::where('email', 'asha@example.com')->count());
    }

    // ---- The confirm link ----

    public function test_confirm_link_confirms_but_does_not_sign_in_and_bad_links_fail(): void
    {
        $this->register();
        $url = $this->confirmUrl();
        $user = User::where('email', 'asha@example.com')->firstOrFail();

        $this->get($url.'x')->assertSessionHas('error', __('account.login.link_bad')); // signature broken
        $this->get(str_replace('/'.$user->id.'/', '/'.($user->id + 1).'/', $url))->assertSessionHas('error', __('account.login.link_bad'));
        $this->assertNull($user->fresh()->email_verified_at);

        $this->get($url)->assertRedirect(url('/login'))->assertSessionHas('status', __('account.login.confirmed'));
        $this->assertGuest();
        $this->assertNotNull($user->fresh()->email_verified_at);

    }

    public function test_confirm_link_stops_working_after_its_time(): void
    {
        $this->register();
        $url = $this->confirmUrl();

        $this->travel(EmailAuthController::CONFIRM_HOURS)->hours();
        $this->travel(1)->minutes();
        $this->get($url)->assertSessionHas('error', __('account.login.link_bad'));
        $this->assertNull(User::where('email', 'asha@example.com')->value('email_verified_at'));
    }

    public function test_confirm_link_keeps_the_language(): void
    {
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->post('/so/register', ['name' => 'Asha Ali', 'email' => 'asha@example.com', 'password' => self::PASS, 'password_confirmation' => self::PASS])
            ->assertRedirect(url('/so/login'));
        $url = $this->confirmUrl();
        $this->assertStringContainsString('lang=so', $url);
        $this->get($url)->assertRedirect(url('/so/login'))->assertSessionHas('status', __('account.login.confirmed', [], 'so'));
    }

    // ---- Sign in ----

    public function test_sign_in_works_and_new_people_finish_their_profile(): void
    {
        $new = User::factory()->create(['email' => 'new@example.com', 'password' => Hash::make(self::PASS), 'email_verified_at' => now()]);
        $this->post('/login/email', ['email' => 'NEW@example.com', 'password' => self::PASS])->assertRedirect(url('/welcome'));
        $this->assertAuthenticatedAs($new);
        $this->assertNotNull($new->fresh()->last_login_at);

        $this->post('/logout');
        $done = $this->customer();
        $this->post('/login/email', ['email' => 'asha@example.com', 'password' => self::PASS])->assertRedirect(url('/'));
        $this->assertAuthenticatedAs($done);
    }

    public function test_wrong_password_and_unknown_email_give_the_same_message(): void
    {
        $this->customer();
        $google = User::factory()->completed()->create(['email' => 'g@example.com', 'password' => null, 'email_verified_at' => now()]);

        foreach ([['asha@example.com', 'wrongPass1'], ['nobody@example.com', self::PASS], ['g@example.com', self::PASS]] as [$email, $pass]) {
            $this->post('/login/email', ['email' => $email, 'password' => $pass])
                ->assertSessionHasErrorsIn('login', ['email' => __('account.login.wrong')]);
            $this->assertGuest();
        }
        $this->assertNull($google->fresh()->password);
    }

    public function test_too_many_wrong_passwords_wait(): void
    {
        $this->customer();
        foreach (range(1, 5) as $i) {
            $this->post('/login/email', ['email' => 'asha@example.com', 'password' => 'wrongPass'.$i])->assertSessionHasErrorsIn('login', 'email');
        }
        $this->post('/login/email', ['email' => 'asha@example.com', 'password' => self::PASS])
            ->assertSessionHasErrorsIn('login', ['email' => __('account.login.too_many', ['seconds' => RateLimiter::availableIn('login:'.hash('sha256', 'asha@example.com').'|'.Ip::key(request()))])]);
        $this->assertGuest();
    }

    public function test_unconfirmed_account_gets_a_new_link_instead_of_signing_in(): void
    {
        User::factory()->create(['email' => 'asha@example.com', 'password' => Hash::make(self::PASS), 'email_verified_at' => null]);

        $this->post('/login/email', ['email' => 'asha@example.com', 'password' => self::PASS])
            ->assertRedirect(url('/login'))->assertSessionHas('status', fn ($s) => str_contains($s, 'confirm your email first'));
        $this->post('/login/email', ['email' => 'asha@example.com', 'password' => self::PASS]); // again at once
        $this->assertGuest();
        Mail::assertSent(ConfirmEmailMail::class, 1); // at most one new link every 2 minutes

        // Wrong password: no link, no hint.
        Mail::fake();
        $this->travel(3)->minutes();
        $this->post('/login/email', ['email' => 'asha@example.com', 'password' => 'wrongPass1'])->assertSessionHasErrorsIn('login', 'email');
        Mail::assertNothingSent();
    }

    public function test_blocked_and_staff_accounts_cannot_sign_in_here(): void
    {
        $this->customer(['banned_at' => now()]);
        $this->post('/login/email', ['email' => 'asha@example.com', 'password' => self::PASS])->assertSessionHas('error', __('account.login.banned'));
        $this->assertGuest();

        User::factory()->create(['email' => 'mod@iibsom.test', 'role' => 'moderator', 'password' => Hash::make(self::PASS), 'email_verified_at' => now()]);
        $this->post('/login/email', ['email' => 'mod@iibsom.test', 'password' => self::PASS])
            ->assertSessionHasErrorsIn('login', ['email' => __('account.login.test_staff')]);
        $this->assertGuest();
    }

    public function test_signed_in_people_are_sent_away_from_the_forms(): void
    {
        $this->actingAs($this->customer());
        $this->get('/register')->assertRedirect();
        $this->get('/forgot-password')->assertRedirect();
    }

    // ---- Forgot password ----

    public function test_forgot_password_gives_the_same_answer_for_everyone_and_mails_only_customers(): void
    {
        $this->customer();
        User::factory()->create(['email' => 'mod@iibsom.test', 'role' => 'moderator']);
        User::factory()->create(['email' => 'bad@example.com', 'banned_at' => now()]);

        $answers = [];
        foreach (['asha@example.com', 'nobody@example.com', 'mod@iibsom.test', 'bad@example.com'] as $email) {
            $this->post('/forgot-password', ['email' => $email])->assertRedirect(url('/login'));
            $answers[] = str_replace($email, 'X', (string) session('status'));
        }

        $this->assertCount(1, array_unique($answers));
        Mail::assertSent(ResetPasswordMail::class, 1);
        Mail::assertSent(ResetPasswordMail::class, fn ($m) => $m->hasTo('asha@example.com'));
    }

    public function test_reset_link_sets_a_new_password_once_and_signs_in(): void
    {
        $user = $this->customer(['email_verified_at' => null, 'remember_token' => 'old-token']);
        $this->post('/forgot-password', ['email' => 'asha@example.com']);
        $url = null;
        Mail::assertSent(ResetPasswordMail::class, function ($m) use (&$url) {
            $url = $m->url;

            return true;
        });
        $this->assertStringContainsString('/auth/reset-password/', (string) $url);
        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);
        $token = basename((string) parse_url((string) $url, PHP_URL_PATH));

        $this->get((string) $url)->assertOk()->assertSee('Choose a new password')->assertSee('noindex', false);

        $data = ['token' => $token, 'email' => 'asha@example.com', 'password' => 'brandNew123', 'password_confirmation' => 'brandNew123', 'lang' => 'en'];
        $this->post('/auth/reset-password', ['password' => 'weak'] + $data)->assertSessionHasErrors('password');
        $this->post('/auth/reset-password', $data)->assertRedirect(url('/'));
        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertTrue(Hash::check('brandNew123', (string) $user->password));
        $this->assertNotNull($user->email_verified_at); // the link proved the email
        $this->assertNotSame('old-token', $user->remember_token);

        // Used once: the same link fails.
        $this->post('/logout');
        $this->post('/auth/reset-password', ['password' => 'another123', 'password_confirmation' => 'another123'] + $data)
            ->assertRedirect(url('/forgot-password'))->assertSessionHas('error', __('account.login.link_bad'));
        $this->assertTrue(Hash::check('brandNew123', (string) $user->fresh()->password));
    }

    public function test_a_wrong_token_never_changes_a_password(): void
    {
        $user = $this->customer();
        $this->post('/auth/reset-password', ['token' => 'made-up', 'email' => 'asha@example.com', 'password' => 'brandNew123', 'password_confirmation' => 'brandNew123'])
            ->assertSessionHas('error', __('account.login.link_bad'));
        $this->assertGuest();
        $this->assertTrue(Hash::check(self::PASS, (string) $user->fresh()->password));
    }

    public function test_staff_password_is_never_reset_here_even_with_a_token(): void
    {
        $staff = User::factory()->create(['email' => 'mod@iibsom.test', 'role' => 'moderator', 'password' => Hash::make(self::PASS)]);
        $token = Password::broker()->createToken($staff);

        $this->post('/auth/reset-password', ['token' => $token, 'email' => 'mod@iibsom.test', 'password' => 'brandNew123', 'password_confirmation' => 'brandNew123'])
            ->assertSessionHas('error', __('account.login.link_bad'));
        $this->assertGuest();
        $this->assertTrue(Hash::check(self::PASS, (string) $staff->fresh()->password));
    }

    // ---- Google and email together ----

    public function test_google_sign_in_removes_a_password_nobody_confirmed(): void
    {
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
        $this->register(['password' => 'attacker99', 'password_confirmation' => 'attacker99']); // someone else signs up with Asha's email

        $google = (new SocialiteUser)->setRaw(['sub' => '777', 'email' => 'asha@example.com', 'email_verified' => true, 'name' => 'Asha'])
            ->map(['id' => '777', 'name' => 'Asha', 'email' => 'asha@example.com', 'avatar' => null]);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($google);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google/callback?state=x&code=y')->assertRedirect();
        $user = User::where('email', 'asha@example.com')->firstOrFail();
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);

        $this->post('/logout');
        $this->post('/login/email', ['email' => 'asha@example.com', 'password' => 'attacker99'])->assertSessionHasErrorsIn('login', 'email');
        $this->assertGuest();
    }

    public function test_google_keeps_a_confirmed_password(): void
    {
        $user = $this->customer();
        $google = (new SocialiteUser)->setRaw(['sub' => '888', 'email' => 'asha@example.com', 'email_verified' => true, 'name' => 'Asha'])
            ->map(['id' => '888', 'name' => 'Asha', 'email' => 'asha@example.com', 'avatar' => null]);
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($google);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
        config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);

        $this->get('/auth/google/callback?state=x&code=y')->assertRedirect();
        $this->assertTrue(Hash::check(self::PASS, (string) $user->fresh()->password));
    }
}
