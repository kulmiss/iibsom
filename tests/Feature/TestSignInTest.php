<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Features\FeatureManager;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Found by the checklist run (row 37): on the owner's own computer nobody can sign up or post,
 * because customers sign in only with Google and there are no Google keys yet.
 * A test sign-in for the owner's computer only (APP_ENV=local), never on the live site.
 */
class TestSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function env(string $env): void
    {
        $this->app['env'] = $env;
        // Outside "testing" Laravel checks the form token; these tests are about who may sign in.
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    private function local(): void
    {
        $this->env('local');
    }

    public function test_sign_up_and_sign_in_on_your_own_computer(): void
    {
        $this->local();
        $this->get('/login')->assertOk()->assertSee('Test sign-in')->assertSee('name="email"', false);

        // A new email = a new account, which goes to "Almost done" like after Google.
        $this->post('/login/test', ['email' => 'Asha@Example.com'])->assertRedirect(url('/welcome'));
        $user = User::where('email', 'asha@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('customer', $user->role);
        $this->assertNotNull($user->email_verified_at);

        // The same email again = the same account.
        auth()->logout();
        $this->post('/login/test', ['email' => 'asha@example.com']);
        $this->assertSame(1, User::where('email', 'asha@example.com')->count());
    }

    public function test_never_on_the_live_site(): void
    {
        foreach (['production', 'staging', 'testing'] as $env) {
            $this->env($env);
            $this->get('/login')->assertOk()->assertDontSee('Test sign-in');
            $this->post('/login/test', ['email' => 'x@example.com'])->assertNotFound();
        }
        $this->assertSame(0, User::where('email', 'x@example.com')->count());
    }

    public function test_not_for_staff_banned_people_or_with_the_switch_off(): void
    {
        $this->local();
        User::factory()->create(['email' => 'mod@iibsom.test', 'role' => 'moderator']);
        $this->post('/login/test', ['email' => 'mod@iibsom.test'])->assertSessionHasErrors('email');
        $this->assertGuest();

        User::factory()->completed()->create(['email' => 'bad@example.com', 'banned_at' => now()]);
        $this->post('/login/test', ['email' => 'bad@example.com'])->assertRedirect(url('/login'));
        $this->assertGuest();

        $this->post('/login/test', ['email' => 'not an email'])->assertSessionHasErrors('email');

        app(FeatureManager::class)->set('login.test_accounts', false);
        $this->get('/login')->assertDontSee('Test sign-in');
        $this->post('/login/test', ['email' => 'y@example.com'])->assertNotFound();
    }

    public function test_google_without_keys_explains_instead_of_an_error_at_google(): void
    {
        config(['services.google.client_id' => '', 'services.google.client_secret' => '']);
        $this->get('/auth/google')->assertRedirect(url('/login'))->assertSessionHas('error', __('account.login.not_set_up'));
    }
}
