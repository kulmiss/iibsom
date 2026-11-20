<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['services.google.client_id' => 'test-id', 'services.google.client_secret' => 'test-secret']);
    }

    private function fakeGoogle(array $raw = [], ?string $id = '1234567890'): void
    {
        $raw += ['sub' => $id, 'email' => 'amina@example.com', 'email_verified' => true, 'name' => 'Amina Ali'];

        $user = (new SocialiteUser)->setRaw($raw)->map([
            'id' => $id,
            'name' => $raw['name'],
            'email' => $raw['email'],
            'avatar' => 'https://lh3.googleusercontent.com/a/photo',
        ]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_login_page_shows_google_button(): void
    {
        $this->get('/login')->assertOk()->assertSee('Continue with Google')->assertSee('/auth/google?lang=en', false);
    }

    public function test_redirects_to_google(): void
    {
        $this->get('/auth/google')->assertRedirect()->assertRedirectContains('accounts.google.com');
    }

    public function test_google_login_switch_off_hides_everything(): void
    {
        app(FeatureManager::class)->set('login.google', false);

        // PR 105: email sign-in still opens the page, without the Google button.
        $this->get('/login')->assertOk()->assertDontSee('data-google-button', false);
        $this->get('/auth/google')->assertNotFound();
        $this->get('/auth/google/callback')->assertNotFound();

        app(FeatureManager::class)->set('login.email', false); // no way left to sign in
        $this->get('/login')->assertNotFound();
        $this->get('/')->assertDontSee('/login', false);
    }

    public function test_new_user_is_created_and_sent_to_welcome(): void
    {
        $this->fakeGoogle();

        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect(url('/welcome'));

        $this->assertAuthenticated();
        $user = User::first();
        $this->assertSame('amina@example.com', $user->email);
        $this->assertSame('1234567890', $user->google_id);
        $this->assertSame('Amina Ali', $user->name);
        $this->assertNull($user->password);
        $this->assertNotNull($user->last_login_at);
        $this->assertFalse($user->hasCompletedProfile());
    }

    public function test_unverified_google_email_is_refused(): void
    {
        $this->fakeGoogle(['email_verified' => false]);

        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect(url('/login'))->assertSessionHas('error');
        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_bad_state_is_refused(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andThrow(new InvalidStateException);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google/callback?code=x&state=forged')->assertRedirect(url('/login'))->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_cancel_on_google_goes_back_to_login(): void
    {
        $this->get('/auth/google/callback?error=access_denied')->assertRedirect(url('/login'))->assertSessionHas('error');
    }

    public function test_returning_user_signs_in_and_goes_home(): void
    {
        $user = User::factory()->completed()->create(['google_id' => '1234567890', 'email' => 'amina@example.com']);
        $this->fakeGoogle();

        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect(url('/'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
    }

    public function test_existing_email_is_linked_to_google(): void
    {
        $user = User::factory()->create(['google_id' => null, 'email' => 'amina@example.com']);
        $this->fakeGoogle();

        $this->get('/auth/google/callback?code=x&state=y');

        $this->assertAuthenticatedAs($user);
        $this->assertSame('1234567890', $user->fresh()->google_id);
    }

    public function test_banned_user_cannot_sign_in(): void
    {
        User::factory()->completed()->banned()->create(['google_id' => '1234567890']);
        $this->fakeGoogle();

        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect(url('/login'))->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_user_banned_while_signed_in_is_signed_out(): void
    {
        $user = User::factory()->completed()->create();
        $this->actingAs($user);
        $user->forceFill(['banned_at' => now()])->save();

        $this->get('/account')->assertRedirect(url('/'));
        $this->assertGuest();
    }

    public function test_session_id_changes_after_sign_in(): void
    {
        $this->fakeGoogle();
        $this->get('/login');
        $before = session()->getId();

        $this->get('/auth/google/callback?code=x&state=y');

        $this->assertNotSame($before, session()->getId());
    }

    public function test_signed_in_user_cannot_open_login_page(): void
    {
        $this->actingAs(User::factory()->completed()->create());

        $this->get('/login')->assertRedirect(url('/'));
    }

    public function test_logout_needs_post_and_csrf(): void
    {
        $this->actingAs(User::factory()->completed()->create());

        $this->get('/logout')->assertNotFound(); // only POST logs out
        $this->assertAuthenticated();
        $this->post('/logout')->assertRedirect(url('/'));
        $this->assertGuest();
    }

    public function test_somali_visitor_comes_back_to_somali_pages(): void
    {
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        app()->forgetScopedInstances();

        $this->get('/auth/google?lang=so');
        $this->fakeGoogle();

        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect(url('/so/welcome'));
    }

    public function test_google_users_never_get_a_password(): void
    {
        $this->fakeGoogle();
        $this->get('/auth/google/callback?code=x&state=y');

        $this->assertNull(User::first()->getAttributes()['password']);
    }
}
