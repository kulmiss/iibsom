<?php

namespace Tests\Feature;

use App\Mail\SavedSearchAlertMail;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\SavedSearch;
use App\Models\User;
use App\Search\SearchCriteria;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SavedSearchesTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private Category $cars;

    private City $mogadishu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->buyer = User::factory()->completed()->create(['email' => 'buyer@iibsom.test']);
        $this->cars = Category::where('slug', 'cars')->firstOrFail();
        $this->mogadishu = City::where('slug', 'mogadishu')->firstOrFail();
    }

    private function on(): void
    {
        app(FeatureManager::class)->set('saved_searches', true);
    }

    private function car(string $title, array $attributes = []): Ad
    {
        return Ad::factory()->live()->create($attributes + [
            'title' => $title,
            'category_id' => $this->cars->id,
            'city_id' => $this->mogadishu->id,
            'published_at' => now(),
        ]);
    }

    private function saveToyotaSearch(): SavedSearch
    {
        $this->actingAs($this->buyer)->from('/cars/mogadishu')
            ->post('/account/searches', ['q' => 'toyota', 'category' => 'cars', 'city' => 'mogadishu', 'max' => 9000])
            ->assertRedirect('/cars/mogadishu')->assertSessionHas('status', 'Search saved. We will email you when new ads match it.');

        return SavedSearch::firstOrFail();
    }

    public function test_off_by_default(): void
    {
        $this->assertFalse(feature('saved_searches'));
        $this->actingAs($this->buyer)->get('/account/searches')->assertNotFound();
        $this->actingAs($this->buyer)->post('/account/searches', ['category' => 'cars'])->assertNotFound();
        $this->actingAs($this->buyer)->get('/cars')->assertOk()->assertDontSee('Save this search');
    }

    public function test_save_a_search_from_a_list_of_ads(): void
    {
        $this->on();
        $this->actingAs($this->buyer)->get('/cars/mogadishu?q=toyota&max=9000')->assertOk()
            ->assertSee('Save this search')
            ->assertSee('name="category" value="cars"', false)
            ->assertSee('name="city" value="mogadishu"', false)
            ->assertSee('name="q" value="toyota"', false);

        $search = $this->saveToyotaSearch();

        $this->assertSame($this->buyer->id, $search->user_id);
        $this->assertEquals(['q' => 'toyota', 'min' => null, 'max' => 9000, 'condition' => null, 'category_id' => $this->cars->id, 'city_id' => $this->mogadishu->id, 'fields' => []], $search->criteria);
        $this->assertSame('"toyota" · Cars · Mogadishu · $0–$9000', $search->label);
        $this->assertStringContainsString('/cars/mogadishu?', $search->url());
    }

    public function test_same_search_twice_too_wide_and_too_many(): void
    {
        $this->on();
        $this->saveToyotaSearch();

        $this->actingAs($this->buyer)->post('/account/searches', ['q' => 'TOYOTA', 'category' => 'cars', 'city' => 'mogadishu', 'max' => 9000])
            ->assertSessionHas('status', 'You already saved this search.');
        $this->actingAs($this->buyer)->post('/account/searches', ['city' => 'mogadishu'])
            ->assertSessionHas('status', 'Choose a category or type a word first, then save the search.');

        for ($i = 2; $i <= SavedSearch::MAX_PER_USER; $i++) {
            $criteria = ['q' => "word{$i}", 'min' => null, 'max' => null, 'condition' => null, 'category_id' => null, 'city_id' => null, 'fields' => []];
            $this->buyer->savedSearches()->create(['label' => "word{$i}", 'criteria' => $criteria, 'hash' => SearchCriteria::hash($criteria), 'checked_until' => now()]);
        }
        $this->actingAs($this->buyer)->post('/account/searches', ['q' => 'one too many'])
            ->assertSessionHas('status', 'You can save up to 20 searches. Delete one first.');
        $this->assertSame(SavedSearch::MAX_PER_USER, SavedSearch::count());
    }

    public function test_unknown_filters_and_off_cities_are_ignored(): void
    {
        $this->on();
        $kismayo = City::where('slug', 'kismayo')->firstOrFail(); // OFF at launch

        $this->actingAs($this->buyer)->post('/account/searches', ['category' => 'cars', 'city' => 'kismayo', 'f' => ['brand' => 'toyota', 'evil' => 'x']]);

        $criteria = SavedSearch::firstOrFail()->criteria;
        $this->assertNull($criteria['city_id']);
        $this->assertNotSame($kismayo->id, $criteria['city_id']);
        $this->assertSame(['brand' => 'toyota'], $criteria['fields']);
    }

    public function test_list_and_delete_only_my_own(): void
    {
        $this->on();
        $search = $this->saveToyotaSearch();
        $other = User::factory()->completed()->create();

        $this->actingAs($this->buyer)->get('/account/searches')->assertOk()->assertSee('"toyota" · Cars · Mogadishu');
        $this->actingAs($this->buyer)->get('/so/account/searches')->assertOk()->assertSee('Raadintayada la kaydiyey');
        $this->actingAs($other)->get('/account/searches')->assertOk()->assertDontSee('toyota');

        $this->actingAs($other)->delete("/account/searches/{$search->id}")->assertNotFound();
        $this->actingAs($this->buyer)->delete("/account/searches/{$search->id}")->assertRedirect(url('/account/searches'));
        $this->assertSame(0, SavedSearch::count());
    }

    public function test_guests_are_sent_to_sign_in_and_lists_stay_cacheable(): void
    {
        $this->on();

        $res = $this->get('/cars')->assertOk()->assertSee('Sign in to save this search');
        $this->assertSame([], $res->headers->getCookies());
        $this->post('/account/searches', ['category' => 'cars'])->assertRedirect(url('/login'));
    }

    public function test_daily_email_lists_only_new_matching_ads_once(): void
    {
        $this->on();
        Mail::fake();
        $this->car('Toyota Vitz from before', ['price' => 5000, 'published_at' => now()->subDay()]);
        $this->travel(-1)->minutes();
        $search = $this->saveToyotaSearch();
        $this->travelBack();

        $this->car('Toyota Hilux new', ['price' => 8000]);
        $this->car('Toyota too expensive', ['price' => 20000]);
        $this->car('Honda Fit new');
        // Posted before the search was saved but approved (published) after: still new.
        $this->car('Toyota Axio approved late', ['price' => 5000, 'created_at' => now()->subDays(2)]);

        $this->artisan('iibsom:saved-search-alerts')->expectsOutputToContain('Sent 1 alert(s).')->assertSuccessful();

        Mail::assertSent(SavedSearchAlertMail::class, function (SavedSearchAlertMail $mail) {
            $titles = $mail->ads->pluck('title')->all();
            sort($titles);

            return $mail->hasTo('buyer@iibsom.test') && $mail->total === 2 && $titles === ['Toyota Axio approved late', 'Toyota Hilux new'];
        });
        $this->assertNotNull($search->fresh()?->last_sent_at);

        // Nothing new: no second email.
        $this->artisan('iibsom:saved-search-alerts')->expectsOutputToContain('Sent 0 alert(s).');
        Mail::assertSentCount(1);
    }

    public function test_alert_email_renders_in_both_languages(): void
    {
        $this->on();
        $search = $this->saveToyotaSearch();
        $ad = $this->car('Toyota Hilux new');

        $html = (new SavedSearchAlertMail($search, collect([$ad]), 1))->render();

        $this->assertStringContainsString('Toyota Hilux new', $html);
        $this->assertStringContainsString('xayeysiis cusub', $html);
        $this->assertStringContainsString('See them all', $html);
        $this->assertStringContainsString(url('/account/searches'), $html);
    }

    public function test_banned_users_off_categories_and_off_switch_get_nothing(): void
    {
        $this->on();
        Mail::fake();
        $this->saveToyotaSearch();
        $this->travel(1)->minutes();
        $this->car('Toyota Hilux new');

        $this->buyer->forceFill(['banned_at' => now()])->save();
        $this->artisan('iibsom:saved-search-alerts')->assertSuccessful();
        $this->buyer->forceFill(['banned_at' => null])->save();

        $this->cars->forceFill(['enabled' => false])->save();
        $this->artisan('iibsom:saved-search-alerts')->assertSuccessful();
        $this->cars->forceFill(['enabled' => true])->save();

        app(FeatureManager::class)->set('saved_searches', false);
        $this->artisan('iibsom:saved-search-alerts')->expectsOutputToContain('switch is OFF')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_runs_every_day_in_the_morning(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'iibsom:saved-search-alerts'));

        $this->assertNotNull($event);
        $this->assertSame('0 8 * * *', $event->expression);
        $this->assertSame('Africa/Mogadishu', $event->timezone);
    }

    public function test_same_search_in_any_order_or_case_is_one_search(): void
    {
        $a = ['q' => 'Toyota', 'min' => null, 'max' => null, 'condition' => null, 'category_id' => 1, 'city_id' => null, 'fields' => ['fuel' => 'petrol', 'brand' => 'toyota']];
        $b = ['q' => 'toyota', 'min' => null, 'max' => null, 'condition' => null, 'category_id' => 1, 'city_id' => null, 'fields' => ['brand' => 'toyota', 'fuel' => 'petrol']];

        $this->assertSame(SearchCriteria::hash($a), SearchCriteria::hash($b));
    }
}
