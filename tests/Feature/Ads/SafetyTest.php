<?php

namespace Tests\Feature\Ads;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Safety\SpamGuard;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->seller = User::factory()->completed()->create(['city_id' => City::where('slug', 'mogadishu')->value('id')]);
    }

    private function postCar(array $overrides = [])
    {
        return $this->actingAs($this->seller)->post('/post/cars', array_merge([
            'title' => 'Toyota Vitz 2012 automatic',
            'description' => 'Very clean car, new tyres, AC works well. No accidents.',
            'price' => 6500,
            'condition' => 'used',
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
            'fields' => ['brand' => 'toyota', 'model' => 'Vitz', 'year' => 2012, 'mileage' => 98000, 'fuel' => 'petrol', 'transmission' => 'automatic'],
        ], $overrides));
    }

    public function test_bad_words_are_whole_words_and_catch_look_alikes(): void
    {
        $this->assertSame('shit', SpamGuard::badWord('This is sh1t'));
        $this->assertSame('shit', SpamGuard::badWord('SHIT!'));
        $this->assertSame('send money first', SpamGuard::badWord('Please send   money first'));
        $this->assertNull(SpamGuard::badWord('Shitake mushrooms, $100, great!'));
        $this->assertNull(SpamGuard::badWord('Classic car, assembled 2010'));
    }

    public function test_ad_with_bad_word_is_refused(): void
    {
        $this->postCar(['description' => 'Good car but send money first before you see it.'])->assertSessionHasErrors('description');
        $this->assertSame(0, Ad::count());
    }

    public function test_bad_word_list_comes_from_settings_and_switch(): void
    {
        app(SettingsManager::class)->set('safety.bad_words', ['badword']);
        $this->postCar(['title' => 'Toyota badword 2012'])->assertSessionHasErrors('title');

        app(FeatureManager::class)->set('safety.bad_words', false);
        $this->postCar(['title' => 'Toyota badword 2012'])->assertSessionHasNoErrors();
    }

    public function test_links_are_refused(): void
    {
        foreach (['Visit https://scam.example for more', 'see www.cheap-cars.net now', 'order at cars4u.com today'] as $i => $text) {
            $this->postCar(['title' => 'Toyota number '.$i, 'description' => 'Very clean car. '.$text.' please.'])->assertSessionHasErrors('description');
        }
        $this->assertFalse(SpamGuard::hasLink('Price is 6.5k, call 0612345678. Engine 1.5 litre.'));

        app(FeatureManager::class)->set('safety.block_links', false);
        $this->postCar(['description' => 'Very clean car. Visit https://example.com for photos.'])->assertSessionHasNoErrors();
    }

    public function test_honeypot_stops_bots(): void
    {
        $this->postCar(['website' => 'http://spam.example'])->assertSessionHasErrors('title');
        $this->assertSame(0, Ad::count());

        app(FeatureManager::class)->set('safety.honeypot', false);
        $this->postCar(['website' => 'x'])->assertSessionHasNoErrors();
    }

    public function test_form_has_hidden_bot_trap(): void
    {
        $this->actingAs($this->seller)->get('/post/cars')->assertSee('name="website"', false);
    }

    public function test_edit_is_checked_too(): void
    {
        $ad = Ad::factory()->live()->create(['user_id' => $this->seller->id, 'category_id' => Category::where('slug', 'cars')->value('id'), 'city_id' => City::where('slug', 'mogadishu')->value('id')]);

        $this->actingAs($this->seller)->put("/account/ads/{$ad->id}", [
            'title' => 'Toyota escort service', 'description' => 'Very clean car, new tyres, AC works well.', 'price' => 1, 'condition' => 'used',
            'city_id' => $ad->city_id, 'fields' => ['brand' => 'toyota', 'model' => 'Vitz', 'year' => 2012, 'fuel' => 'petrol', 'transmission' => 'automatic'],
        ])->assertSessionHasErrors('title');
    }

    public function test_admin_can_edit_the_word_list(): void
    {
        $owner = User::factory()->create();
        $owner->forceFill(['role' => 'super_admin', 'two_factor_secret' => str_repeat('A', 32), 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => []])->save();
        $this->actingAs($owner)->withSession(['admin.2fa_passed' => $owner->id, 'admin.last_seen' => now()->getTimestamp()])
            ->putJson('/admin/api/settings/general', ['ads_expiry_days' => 60, 'ads_max_per_day' => 5, 'ads_max_photos' => 8, 'languages' => ['en'], 'bad_words' => "Foo\nbar baz\n\nfoo"])
            ->assertOk()->assertJsonPath('bad_words', "foo\nbar baz");
    }
}
