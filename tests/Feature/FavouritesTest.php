<?php

namespace Tests\Feature;

use App\Enums\AdStatus;
use App\Http\Controllers\Account\FavouriteController;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FavouritesTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->buyer = User::factory()->completed()->create();
    }

    private function ad(array $attributes = []): Ad
    {
        return Ad::factory()->live()->create($attributes + [
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);
    }

    private function on(): void
    {
        app(FeatureManager::class)->set('favourites', true);
    }

    public function test_off_by_default_and_hidden_everywhere(): void
    {
        $ad = $this->ad();

        $this->assertFalse(feature('favourites'));
        $this->actingAs($this->buyer)->get('/account/saved')->assertNotFound();
        $this->actingAs($this->buyer)->postJson("/favourites/{$ad->id}")->assertNotFound();
        $this->actingAs($this->buyer)->get($ad->url())->assertOk()->assertDontSee('data-favourite', false)->assertDontSee(url('/account/saved'));
    }

    public function test_save_and_unsave_with_javascript(): void
    {
        $this->on();
        $ad = $this->ad();

        $this->actingAs($this->buyer)->get($ad->url())->assertOk()->assertSee('aria-pressed="false"', false)->assertSee('Save this ad');

        $this->actingAs($this->buyer)->postJson("/favourites/{$ad->id}")->assertOk()->assertJson(['saved' => true]);
        $this->assertDatabaseHas('favourites', ['user_id' => $this->buyer->id, 'ad_id' => $ad->id]);
        $this->actingAs($this->buyer)->get($ad->url())->assertOk()->assertSee('aria-pressed="true"', false)->assertSee('Remove from saved ads');

        $this->actingAs($this->buyer)->postJson("/favourites/{$ad->id}")->assertOk()->assertJson(['saved' => false]);
        $this->assertDatabaseMissing('favourites', ['user_id' => $this->buyer->id, 'ad_id' => $ad->id]);
    }

    public function test_save_without_javascript_goes_back_with_a_message(): void
    {
        $this->on();
        $ad = $this->ad();

        $this->actingAs($this->buyer)->from($ad->url())->post("/favourites/{$ad->id}")
            ->assertRedirect($ad->url())->assertSessionHas('status', 'Saved. Find it in Saved ads.');
    }

    public function test_guests_are_sent_to_sign_in_and_their_page_stays_cacheable(): void
    {
        $this->on();
        $ad = $this->ad();

        $res = $this->get($ad->url())->assertOk()->assertSee('href="'.url('/login').'"', false)->assertDontSee('data-favourite', false);
        $this->assertSame([], $res->headers->getCookies());
        $this->post("/favourites/{$ad->id}")->assertRedirect(url('/login'));
        $this->get('/account/saved')->assertRedirect(url('/login'));
    }

    public function test_only_live_ads_can_be_saved_but_old_ones_can_be_removed(): void
    {
        $this->on();
        $pending = $this->ad(['status' => AdStatus::Pending]);
        $this->actingAs($this->buyer)->postJson("/favourites/{$pending->id}")->assertNotFound();

        $ad = $this->ad();
        $this->actingAs($this->buyer)->postJson("/favourites/{$ad->id}")->assertJson(['saved' => true]);
        $ad->forceFill(['status' => AdStatus::Sold])->save();

        $this->actingAs($this->buyer)->get('/account/saved')->assertOk()->assertSee($ad->title)->assertSee('No longer available');
        $this->actingAs($this->buyer)->postJson("/favourites/{$ad->id}")->assertJson(['saved' => false]);
    }

    public function test_saved_page_shows_only_my_ads_newest_first(): void
    {
        $this->on();
        $first = $this->ad(['title' => 'Toyota Vitz first saved']);
        $second = $this->ad(['title' => 'Honda Fit second saved']);
        $other = $this->ad(['title' => 'Someone else saved this']);

        $this->travel(-5)->minutes();
        $this->actingAs($this->buyer)->postJson("/favourites/{$first->id}");
        $this->travelBack();
        $this->actingAs($this->buyer)->postJson("/favourites/{$second->id}");
        $this->actingAs(User::factory()->completed()->create())->postJson("/favourites/{$other->id}");

        $this->actingAs($this->buyer)->get('/account/saved')->assertOk()
            ->assertSeeInOrder(['Honda Fit second saved', 'Toyota Vitz first saved'])
            ->assertDontSee('Someone else saved this');
        $this->actingAs($this->buyer)->get('/so/account/saved')->assertOk()->assertSee('Xayeysiisyada la kaydiyey');
    }

    public function test_deleted_ads_leave_the_list(): void
    {
        $this->on();
        $ad = $this->ad(['title' => 'Deleted later']);
        $this->actingAs($this->buyer)->postJson("/favourites/{$ad->id}");

        $ad->delete(); // soft delete (seller deleted it)

        $this->actingAs($this->buyer)->get('/account/saved')->assertOk()->assertDontSee('Deleted later')->assertSee('No saved ads yet');
    }

    public function test_limit_per_user(): void
    {
        $this->on();
        $ad = $this->ad();
        $filler = $this->ad();
        // Fill the list up to the limit (same ad ids are not allowed, so use raw rows with many ads).
        $ids = Ad::factory()->live()->count(FavouriteController::MAX - 1)->create([
            'category_id' => $filler->category_id, 'city_id' => $filler->city_id, 'user_id' => $filler->user_id,
        ])->pluck('id')->push($filler->id);
        DB::table('favourites')->insert($ids->map(fn ($id) => ['user_id' => $this->buyer->id, 'ad_id' => $id, 'created_at' => now(), 'updated_at' => now()])->all());

        $this->actingAs($this->buyer)->postJson("/favourites/{$ad->id}")->assertStatus(422)->assertJsonPath('message', 'You can save up to 500 ads. Remove some first.');
    }

    public function test_menu_links_go_to_saved_ads(): void
    {
        $this->on();

        $this->actingAs($this->buyer)->get('/')->assertOk()->assertSee('href="'.url('/account/saved').'"', false);
        $this->get('/')->assertOk()->assertSee('href="'.url('/account/saved').'"', false);
    }
}
