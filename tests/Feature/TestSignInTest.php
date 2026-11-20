<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\TestSignInController;
use App\Models\User;
use App\Support\Features\FeatureManager;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
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

    public function test_production_never_opens_it_even_with_the_switch_on_and_a_local_address(): void
    {
        app(FeatureManager::class)->set('login.test_accounts', true);
        $this->env('production');

        $this->get('http://127.0.0.1:8095/login')->assertOk()->assertDontSee('Test sign-in');
        $this->post('http://127.0.0.1:8095/login/test', ['email' => 'x@example.com'])->assertNotFound();
        $this->assertFalse(TestSignInController::routeAllowed());
        $this->assertSame(0, User::where('email', 'x@example.com')->count());
    }

    public function test_a_wrong_app_env_on_the_real_domain_still_keeps_it_closed(): void
    {
        $this->local(); // APP_ENV=local by mistake on the live server
        foreach (['https://iibsom.com', 'https://www.iibsom.com', 'http://203.0.113.5'] as $site) {
            $this->get($site.'/login')->assertOk()->assertDontSee('Test sign-in');
            $this->post($site.'/login/test', ['email' => 'x@example.com'])->assertNotFound();
        }
        $this->assertSame(0, User::where('email', 'x@example.com')->count());

        // The owner's own computer still works.
        foreach (['http://127.0.0.1:8095', 'http://localhost', 'http://iibsom.test', 'http://[::1]:8095'] as $site) {
            $this->get($site.'/login')->assertOk()->assertSee('Test sign-in');
        }
    }

    public function test_the_route_does_not_exist_when_the_site_starts_in_production(): void
    {
        // A fresh start with APP_ENV=production (like the live server): the route list has no test sign-in.
        $run = Process::path(base_path())
            ->env(['APP_ENV' => 'production', 'APP_DEBUG' => 'false'])
            ->run([PHP_BINARY, 'artisan', 'route:list', '--json', '--path=login']);

        $this->assertTrue($run->successful(), $run->errorOutput());
        $names = array_column((array) json_decode($run->output(), true), 'name');
        $this->assertContains('login', $names);
        $this->assertContains('login.email', $names);
        $this->assertNotContains('login.test', $names);
        $this->assertNotContains('loc.login.test', $names);
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
