<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Cache\PageCache;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardsAndListsTest extends TestCase
{
    use RefreshDatabase;

    private Category $cars;

    private City $mogadishu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->cars = Category::where('slug', 'cars')->firstOrFail();
        $this->mogadishu = City::where('slug', 'mogadishu')->firstOrFail();
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

    private function switches(array $on): void
    {
        foreach ($on as $key => $value) {
            app(FeatureManager::class)->set($key, $value);
        }
    }

    // ---- New / Used and the short text ----

    public function test_cards_show_condition_and_a_short_escaped_text_for_the_list_view(): void
    {
        $this->car('Toyota Vitz', ['condition' => 'used', 'description' => "Clean car.\n\nNew <b>tyres</b>, ".str_repeat('very good ', 40)]);

        $page = $this->get('/cars')->assertOk();
        // PR 103: a badge on the bottom line, before the city and time (no separate line under the title).
        $page->assertSeeInOrder(['data-card-title>Toyota Vitz', 'data-card-condition>Used</span>', 'Mogadishu ·'], false)
            ->assertDontSee('>Used</p>', false)
            ->assertSee('Clean car. New &lt;b&gt;tyres&lt;/b&gt;', false)
            ->assertDontSee('<b>tyres</b>', false)
            ->assertDontSee(str_repeat('very good ', 20), false); // cut at 160 letters

        $this->get('/so/cars')->assertOk()->assertSee('data-card-condition>La isticmaalay</span>', false);

        $this->switches(['ads.card_details' => false]);
        $this->get('/cars')->assertOk()->assertDontSee('data-card-condition', false)->assertDontSee('Clean car.', false);
    }

    public function test_a_card_without_condition_or_description_shows_no_empty_lines(): void
    {
        $this->car('Plain Hilux', ['condition' => null, 'description' => '']);

        $html = $this->get('/cars')->assertOk()->getContent();
        $this->assertStringNotContainsString('line-clamp-2 text-sm text-stone-600"></p>', (string) $html);
        $this->assertStringNotContainsString('data-card-condition', (string) $html);
    }

    // ---- Save button on cards ----

    public function test_save_button_on_cards_only_with_both_switches(): void
    {
        $ad = $this->car('Toyota Vitz');

        $this->switches(['favourites' => false]);
        $this->get('/cars')->assertOk()->assertDontSee('data-card-save', false)->assertDontSee('name="card-save"', false);

        $this->switches(['favourites' => true]);
        $this->get('/cars')->assertOk()->assertSee('data-card-save="'.$ad->id.'"', false)->assertSee('name="card-save"', false);

        $this->switches(['ads.card_save' => false]);
        $this->get('/cars')->assertOk()->assertDontSee('data-card-save', false)->assertDontSee('name="card-save"', false);
    }

    public function test_save_button_on_cards_is_small_and_its_words_are_said_once(): void
    {
        // PR 112: the button is on every card; its look is a CSS class and its words come once per page.
        $ad = $this->car('Toyota Vitz');
        $this->car('Honda Fit');
        $html = (string) $this->get('/cars')->assertOk()->getContent();

        $this->assertStringContainsString('<button type="button" hidden data-card-save="'.$ad->id.'" aria-pressed="false" class="card-save"></button>', $html);
        $this->assertSame(1, substr_count($html, 'name="card-save-labels"'));
        $this->assertStringContainsString('data-on="'.e(__('ads.saved.unsave_label')).'" data-off="'.e(__('ads.saved.save_label')).'"', $html);
        $this->assertStringNotContainsString('data-label-on="'.e(__('ads.saved.unsave_label')).': Toyota Vitz"', $html);
    }

    public function test_guests_get_the_same_cacheable_page_with_no_saved_ads_and_no_token(): void
    {
        $this->car('Toyota Vitz');
        $this->switches(['favourites' => true]);

        $res = $this->get('/cars')->assertOk();
        $res->assertSee('<meta name="card-save" content="" data-login="'.url('/login').'">', false)
            ->assertDontSee('data-token=', false);
        $this->assertEmpty($res->headers->getCookies(), 'guest list pages stay cookie-free');
        $this->assertStringContainsString('public', (string) $res->headers->get('Cache-Control'));
    }

    public function test_signed_in_people_get_their_saved_ads_and_can_toggle_with_the_header_token(): void
    {
        $this->switches(['favourites' => true]);
        $saved = $this->car('Saved Vitz');
        $other = $this->car('Other Hilux');
        $someoneElses = $this->car('Someone else saved this');
        $buyer = User::factory()->completed()->create();
        $buyer->favourites()->attach($saved->id);
        User::factory()->completed()->create()->favourites()->attach($someoneElses->id);

        $page = $this->actingAs($buyer)->get('/cars')->assertOk();
        $page->assertSee('<meta name="card-save" content="'.$saved->id.'" data-url="'.url('/favourites/0').'" data-token="', false);

        // What card-save.js sends: JSON + X-CSRF-TOKEN header (no form field).
        $token = session()->token();
        $this->withHeaders(['X-CSRF-TOKEN' => $token, 'Accept' => 'application/json'])
            ->post('/favourites/'.$other->id)->assertOk()->assertJsonPath('saved', true);
        $this->assertTrue($buyer->favourites()->whereKey($other->id)->exists());
    }

    // ---- Show more ----

    public function test_show_more_appears_when_there_is_a_next_page_and_keeps_the_page_links(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->car("Car number $i", ['published_at' => now()->subMinutes($i)]);
        }

        $first = $this->get('/cars')->assertOk();
        $first->assertSee('data-show-more hidden', false)->assertSee('data-pages', false)->assertSee('rel="next"', false)
            ->assertSee('Show more ads');
        $this->get('/so/cars')->assertOk()->assertSee('Tus xayeysiisyo kale');

        // The next page holds the 25th ad in the same list markup the button reads.
        $this->get('/cars?page=2')->assertOk()->assertSee('data-results', false)->assertSee('Car number 25')->assertDontSee('data-show-more', false);

        $this->switches(['lists.show_more' => false]);
        $this->get('/cars')->assertOk()->assertDontSee('data-show-more', false)->assertSee('rel="next"', false);
    }

    public function test_no_show_more_on_a_single_page(): void
    {
        $this->car('Only one');

        $this->get('/cars')->assertOk()->assertDontSee('data-show-more', false);
    }

    // ---- Popular now ----

    public function test_popular_now_lists_the_most_viewed_recent_ads_in_order(): void
    {
        $this->switches(['ad_stats' => true]);
        $this->car('Fourth most', ['views' => 40]);
        $this->car('Most viewed', ['views' => 900]);
        $this->car('Second most', ['views' => 300]);
        $this->car('Third most', ['views' => 120]);
        $this->car('Old but viewed', ['views' => 5000, 'published_at' => now()->subDays(31)]);
        $this->car('Never viewed', ['views' => 0]);
        $this->car('Waiting', ['views' => 9999, 'status' => 'pending']);

        $home = $this->get('/')->assertOk();
        $home->assertSee('Popular now')->assertSeeInOrder(['Popular now', 'Most viewed', 'Second most', 'Third most', 'Fourth most', 'Latest ads']);
        $section = (string) str((string) $home->getContent())->between('id="popular-now"', 'Latest ads');
        $this->assertStringNotContainsString('Old but viewed', $section);
        $this->assertStringNotContainsString('Never viewed', $section);
        $this->assertStringNotContainsString('Waiting', $section);

        $this->get('/so')->assertOk()->assertSee('Hadda caan ah');
    }

    public function test_popular_now_needs_four_ads_and_both_switches(): void
    {
        foreach (['A', 'B', 'C'] as $i => $t) {
            $this->car("Car $t", ['views' => 10 + $i]);
        }
        $this->switches(['ad_stats' => true]);
        $this->get('/')->assertOk()->assertDontSee('id="popular-now"', false); // only 3

        $this->car('Car D', ['views' => 50]);
        PageCache::bump();
        $this->get('/')->assertOk()->assertSee('id="popular-now"', false);

        $this->switches(['home.popular_now' => false]);
        $this->get('/')->assertOk()->assertDontSee('id="popular-now"', false);

        $this->switches(['home.popular_now' => true, 'ad_stats' => false]);
        $this->get('/')->assertOk()->assertDontSee('id="popular-now"', false);
    }
}
