<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WelcomeTourTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
    }

    private function finishWelcome(User $user): TestResponse
    {
        return $this->actingAs($user)->followingRedirects()->post('/welcome', [
            'name' => 'Amina Ali',
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
            'phone' => '615123456',
            'terms' => '1',
        ]);
    }

    public function test_tour_shows_once_after_the_first_sign_in(): void
    {
        $user = User::factory()->create();

        $this->finishWelcome($user)->assertOk()
            ->assertSee('<dialog data-tour', false)
            ->assertSeeInOrder(['Find it near you', 'Sell for free', 'Stay safe'])
            ->assertSee('Step 1 of 3');

        // Only once: the next page has no tour.
        $this->actingAs($user)->get('/')->assertOk()->assertDontSee('data-tour', false);
    }

    public function test_saving_the_profile_later_does_not_show_it(): void
    {
        $user = User::factory()->completed()->create();

        $this->actingAs($user)->followingRedirects()->post('/account/profile', [
            'name' => 'Amina Ali',
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
            'phone' => '615123456',
        ])->assertOk()->assertDontSee('data-tour', false);
    }

    public function test_account_page_opens_the_tour_again(): void
    {
        $user = User::factory()->completed()->create();

        $this->actingAs($user)->get('/account')->assertOk()->assertSee('href="'.url('/account?tour=1').'"', false)->assertDontSee('<dialog', false);
        $this->actingAs($user)->get('/account?tour=1')->assertOk()->assertSee('<dialog data-tour', false);
        $this->actingAs($user)->get('/so/account?tour=1')->assertOk()->assertSee('Ka hel meel kuu dhow')->assertSee('Tallaabada 1 ee 3');
    }

    public function test_guests_never_get_the_tour(): void
    {
        $this->get('/?tour=1')->assertOk()->assertDontSee('data-tour', false);
    }

    public function test_switch_off_hides_the_tour_and_the_link(): void
    {
        app(FeatureManager::class)->set('site.welcome_tour', false);
        $user = User::factory()->create();

        $this->finishWelcome($user)->assertOk()->assertDontSee('data-tour', false);
        $this->actingAs($user)->get('/account?tour=1')->assertOk()
            ->assertDontSee('data-tour', false)
            ->assertDontSee('Quick tour');
    }
}
