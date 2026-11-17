<?php

namespace Tests\Feature\Auth;

use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private City $mogadishu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->mogadishu = City::where('slug', 'mogadishu')->first();
    }

    private function newUser(): User
    {
        return User::factory()->create(['name' => 'Amina Ali']);
    }

    public function test_welcome_needs_sign_in(): void
    {
        $this->get('/welcome')->assertRedirect(url('/login'));
        $this->post('/welcome', [])->assertRedirect(url('/login'));
    }

    public function test_welcome_form_shows_name_city_phone_terms(): void
    {
        $this->actingAs($this->newUser())->get('/welcome')
            ->assertOk()
            ->assertSee('value="Amina Ali"', false)
            ->assertSee('Mogadishu')
            ->assertSee('+252')
            ->assertSee('I use WhatsApp on this number')
            ->assertSee('name="terms"', false);
    }

    public function test_completing_welcome_saves_profile(): void
    {
        $user = $this->newUser();

        $this->actingAs($user)->post('/welcome', [
            'name' => 'Amina Ali',
            'city_id' => $this->mogadishu->id,
            'phone' => '061 5 123 456',
            'phone_has_whatsapp' => '1',
            'terms' => '1',
        ])->assertRedirect(url('/'))->assertSessionHas('status');

        $user->refresh();
        $this->assertSame('+252615123456', $user->phone);
        $this->assertTrue($user->phone_has_whatsapp);
        $this->assertSame($this->mogadishu->id, $user->city_id);
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertTrue($user->hasCompletedProfile());
    }

    public function test_terms_must_be_accepted(): void
    {
        $this->actingAs($this->newUser())->post('/welcome', [
            'name' => 'Amina', 'city_id' => $this->mogadishu->id, 'phone' => '615123456',
        ])->assertSessionHasErrors('terms');
    }

    public function test_bad_phone_numbers_are_refused(): void
    {
        foreach (['12345', '+254712345678', '0815123456', 'abc', '61512345'] as $bad) {
            $this->actingAs($this->newUser())->post('/welcome', [
                'name' => 'Amina', 'city_id' => $this->mogadishu->id, 'phone' => $bad, 'terms' => '1',
            ])->assertSessionHasErrors('phone');
        }
    }

    public function test_city_must_be_switched_on(): void
    {
        $kismayo = City::where('slug', 'kismayo')->first(); // OFF

        $this->actingAs($this->newUser())->post('/welcome', [
            'name' => 'Amina', 'city_id' => $kismayo->id, 'phone' => '615123456', 'terms' => '1',
        ])->assertSessionHasErrors('city_id');
    }

    public function test_html_in_name_is_removed(): void
    {
        $user = $this->newUser();

        $this->actingAs($user)->post('/welcome', [
            'name' => '<b>Amina</b><script>x</script>', 'city_id' => $this->mogadishu->id, 'phone' => '615123456', 'terms' => '1',
        ]);

        $this->assertSame('Aminax', $user->fresh()->name);
    }

    public function test_user_cannot_ban_themselves_free_or_change_hidden_fields(): void
    {
        $user = User::factory()->completed()->banned()->create();
        $user->forceFill(['banned_at' => null])->save(); // not banned, but try mass-assigning

        $this->actingAs($user)->post('/account/profile', [
            'name' => 'Amina', 'city_id' => $this->mogadishu->id, 'phone' => '615123456',
            'banned_at' => null, 'google_id' => 'hacked', 'email' => 'x@evil.test', 'profile_completed_at' => null,
        ]);

        $fresh = $user->fresh();
        $this->assertNotSame('hacked', $fresh->google_id);
        $this->assertNotSame('x@evil.test', $fresh->email);
        $this->assertTrue($fresh->hasCompletedProfile());
    }

    public function test_incomplete_profile_is_sent_to_welcome(): void
    {
        $this->actingAs($this->newUser())->get('/account')->assertRedirect(url('/welcome'));
    }

    public function test_account_page_and_profile_edit(): void
    {
        $user = User::factory()->completed()->create(['name' => 'Amina', 'city_id' => $this->mogadishu->id, 'phone' => '+252615123456']);

        $this->actingAs($user)->get('/account')
            ->assertOk()->assertSee('Amina')->assertSee('+252 61 512 3456')->assertSee('Log out');

        $this->actingAs($user)->post('/account/profile', [
            'name' => 'Amina Hassan', 'city_id' => $this->mogadishu->id, 'phone' => '+252 77 1234567', 'phone_has_whatsapp' => '0',
        ])->assertRedirect(url('/account'));

        $user->refresh();
        $this->assertSame('Amina Hassan', $user->name);
        $this->assertSame('+252771234567', $user->phone);
        $this->assertFalse($user->phone_has_whatsapp);
    }

    public function test_completed_user_skips_welcome(): void
    {
        $this->actingAs(User::factory()->completed()->create())->get('/welcome')->assertRedirect(url('/account'));
    }

    public function test_header_shows_signed_in_user(): void
    {
        $user = User::factory()->completed()->create(['name' => 'Zahra']);

        $this->actingAs($user)->get('/')->assertSee('title="My account"', false)->assertSee('Z<span class="sr-only">My account</span>', false);
    }

    public function test_guest_header_links_to_sign_in(): void
    {
        $this->get('/')->assertSee('href="'.url('/login').'"', false);
    }
}
