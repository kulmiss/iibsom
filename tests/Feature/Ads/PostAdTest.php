<?php

namespace Tests\Feature\Ads;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\AdPhoto;
use App\Models\Area;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Features\FeatureManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostAdTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private City $mogadishu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->mogadishu = City::where('slug', 'mogadishu')->first();
        $this->seller = User::factory()->completed()->create(['city_id' => $this->mogadishu->id]);
    }

    private function carAd(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Toyota Vitz 2012 automatic',
            'description' => 'Very clean car, new tyres, AC works well. No accidents.',
            'price' => 6500,
            'condition' => 'used',
            'city_id' => $this->mogadishu->id,
            'area_id' => Area::where('slug', 'hodan')->value('id'),
            'fields' => ['brand' => 'toyota', 'model' => 'Vitz', 'year' => 2012, 'mileage' => 98000, 'fuel' => 'petrol', 'transmission' => 'automatic'],
        ], $overrides);
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/post')->assertRedirect(url('/login'));
        $this->post('/post/cars', $this->carAd())->assertRedirect(url('/login'));
        $this->assertSame(0, Ad::count());
    }

    public function test_incomplete_profile_goes_to_welcome_first(): void
    {
        $this->actingAs(User::factory()->create())->get('/post')->assertRedirect(url('/welcome'));
    }

    public function test_choose_category_page_lists_sub_categories(): void
    {
        $this->actingAs($this->seller)->get('/post')
            ->assertOk()->assertSee('What are you selling?')->assertSee('Cars')->assertSee(url('/post/cars'), false);
    }

    public function test_form_shows_the_category_fields(): void
    {
        $this->actingAs($this->seller)->get('/post/cars')
            ->assertOk()
            ->assertSee('name="fields[brand]"', false)
            ->assertSee('Toyota')
            ->assertSee('name="fields[mileage]"', false)
            ->assertSee('name="condition"', false)
            ->assertSee('Hodan'); // areas for Mogadishu in the data
    }

    public function test_jobs_form_has_no_condition(): void
    {
        $this->actingAs($this->seller)->get('/post/job-vacancies')->assertOk()->assertDontSee('name="condition"', false);
    }

    public function test_main_category_or_disabled_category_cannot_take_ads(): void
    {
        $this->actingAs($this->seller)->get('/post/vehicles')->assertNotFound();

        Category::where('slug', 'cars')->first()->update(['enabled' => false]);
        $this->actingAs($this->seller)->get('/post/cars')->assertNotFound();
    }

    public function test_posting_creates_a_pending_ad(): void
    {
        $this->actingAs($this->seller)->post('/post/cars', $this->carAd())->assertRedirect();

        $ad = Ad::first();
        $this->assertSame(AdStatus::Pending, $ad->status);
        $this->assertSame($this->seller->id, $ad->user_id);
        $this->assertSame(6500, $ad->price);
        $this->assertEqualsCanonicalizing(['brand' => 'toyota', 'model' => 'Vitz', 'year' => 2012, 'mileage' => 98000, 'fuel' => 'petrol', 'transmission' => 'automatic'], $ad->fields);
        $this->assertSame(2012, $ad->fields['year']); // numbers stay numbers
        $this->assertNull($ad->published_at);
        $this->assertSame('toyota-vitz-2012-automatic', $ad->slug);
    }

    public function test_done_page_says_waiting_for_approval(): void
    {
        $this->actingAs($this->seller)->post('/post/cars', $this->carAd());
        $ad = Ad::first();

        $this->actingAs($this->seller)->get('/post/done/'.$ad->id)->assertOk()->assertSee('Our team checks every ad');
    }

    public function test_someone_else_cannot_see_your_done_page(): void
    {
        $this->actingAs($this->seller)->post('/post/cars', $this->carAd());
        $other = User::factory()->completed()->create();

        $this->actingAs($other)->get('/post/done/'.Ad::first()->id)->assertNotFound();
    }

    public function test_approval_switch_off_makes_ads_live_with_expiry(): void
    {
        app(FeatureManager::class)->set('ads.approval', false);

        $this->actingAs($this->seller)->post('/post/cars', $this->carAd());

        $ad = Ad::first();
        $this->assertSame(AdStatus::Live, $ad->status);
        $this->assertEqualsWithDelta(now()->addDays(60)->timestamp, $ad->expires_at->timestamp, 5);
    }

    public function test_required_fields_and_custom_field_rules(): void
    {
        $this->actingAs($this->seller)->post('/post/cars', $this->carAd([
            'title' => 'Car', 'description' => 'short', 'condition' => 'broken',
            'fields' => ['brand' => 'not-a-brand', 'year' => 1800],
        ]))->assertSessionHasErrors(['title', 'description', 'condition', 'fields.brand', 'fields.year']);

        $this->assertSame(0, Ad::count());
    }

    public function test_price_or_negotiable_is_needed(): void
    {
        $this->actingAs($this->seller)->post('/post/cars', $this->carAd(['price' => null]))->assertSessionHasErrors('price');

        $this->actingAs($this->seller)->post('/post/cars', $this->carAd(['price' => null, 'price_negotiable' => '1']))->assertSessionHasNoErrors();
        $this->assertTrue(Ad::first()->price_negotiable);
    }

    public function test_city_must_be_on_and_area_must_belong_to_city(): void
    {
        $kismayo = City::where('slug', 'kismayo')->first();
        $this->actingAs($this->seller)->post('/post/cars', $this->carAd(['city_id' => $kismayo->id]))->assertSessionHasErrors('city_id');

        $otherCityArea = Area::create(['city_id' => $kismayo->id, 'name' => ['en' => 'X'], 'slug' => 'x']);
        $this->actingAs($this->seller)->post('/post/cars', $this->carAd(['area_id' => $otherCityArea->id]))->assertSessionHasErrors('area_id');
    }

    public function test_status_and_owner_cannot_be_forced_by_the_form(): void
    {
        $victim = User::factory()->completed()->create();

        $this->actingAs($this->seller)->post('/post/cars', $this->carAd([
            'status' => 'live', 'user_id' => $victim->id, 'published_at' => now(), 'is_demo' => 1, 'reviewed_by' => $victim->id,
        ]));

        $ad = Ad::first();
        $this->assertSame(AdStatus::Pending, $ad->status);
        $this->assertSame($this->seller->id, $ad->user_id);
        $this->assertFalse($ad->is_demo);
        $this->assertNull($ad->reviewed_by);
    }

    public function test_html_is_removed_from_text(): void
    {
        $this->actingAs($this->seller)->post('/post/cars', $this->carAd([
            'title' => '<script>alert(1)</script>Nice car for sale',
            'description' => '<img src=x onerror=alert(1)>Very clean car, new tyres, AC works.',
        ]));

        $ad = Ad::first();
        $this->assertSame('alert(1)Nice car for sale', $ad->title);
        $this->assertStringNotContainsString('<img', $ad->description);
    }

    public function test_unknown_custom_fields_are_dropped(): void
    {
        $this->actingAs($this->seller)->post('/post/cars', $this->carAd(['fields' => ['brand' => 'toyota', 'hack' => 'x']]));

        $this->assertSame(['brand' => 'toyota'], Ad::first()->fields);
    }

    public function test_same_ad_twice_is_refused(): void
    {
        $this->actingAs($this->seller)->post('/post/cars', $this->carAd());
        $this->actingAs($this->seller)->post('/post/cars', $this->carAd())->assertSessionHasErrors('title');

        $this->assertSame(1, Ad::count());
    }

    public function test_daily_limit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($this->seller)->post('/post/cars', $this->carAd(['title' => "Toyota number {$i} for sale"]))->assertSessionHasNoErrors();
        }

        $this->actingAs($this->seller)->post('/post/cars', $this->carAd(['title' => 'Toyota number six for sale']))->assertSessionHasErrors('title');
        $this->assertSame(5, Ad::count());
    }

    public function test_posting_switch_off_hides_everything(): void
    {
        app(FeatureManager::class)->set('ads.posting', false);

        $this->actingAs($this->seller)->get('/post')->assertNotFound();
        $this->actingAs($this->seller)->post('/post/cars', $this->carAd())->assertNotFound();
        $this->actingAs($this->seller)->post('/post-photos')->assertNotFound();
    }

    public function test_photos_are_attached_in_order_only_if_yours(): void
    {
        $mine1 = AdPhoto::create(['user_id' => $this->seller->id, 'status' => 'ready']);
        $mine2 = AdPhoto::create(['user_id' => $this->seller->id, 'status' => 'ready']);
        $theirs = AdPhoto::create(['user_id' => User::factory()->create()->id, 'status' => 'ready']);

        $this->actingAs($this->seller)->post('/post/cars', $this->carAd(['photos' => [$mine2->id, $theirs->id, $mine1->id]]));

        $ad = Ad::first();
        $this->assertSame([$mine2->id, $mine1->id], $ad->photos()->pluck('id')->all());
        $this->assertNull($theirs->fresh()->ad_id);
        $this->assertSame(0, $mine2->fresh()->sort);
    }

    public function test_at_most_eight_photos(): void
    {
        $ids = collect(range(1, 9))->map(fn () => AdPhoto::create(['user_id' => $this->seller->id])->id)->all();

        $this->actingAs($this->seller)->post('/post/cars', $this->carAd(['photos' => $ids]))->assertSessionHasErrors('photos');
    }

    public function test_expire_command(): void
    {
        $old = Ad::factory()->live()->create(['expires_at' => now()->subHour()]);
        $fresh = Ad::factory()->live()->create();

        $this->artisan('iibsom:ads:expire')->assertSuccessful();

        $this->assertSame(AdStatus::Expired, $old->fresh()->status);
        $this->assertSame(AdStatus::Live, $fresh->fresh()->status);
    }

    public function test_sell_button_goes_to_post_page(): void
    {
        $this->get('/')->assertSee('href="'.url('/post').'"', false);
    }
}
