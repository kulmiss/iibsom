<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Support\Features\FeatureManager;
use App\Support\Search\SearchTerms;
use App\Support\Settings\SettingsManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SearchSuggestTest extends TestCase
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

    public function test_terms_come_from_live_titles_only(): void
    {
        $this->car('Toyota Corolla 2015 for sale');
        $this->car('Toyota Corolla clean');
        $this->car('Secret Lamborghini', ['status' => 'pending']);

        SearchTerms::rebuild();
        $terms = DB::table('search_terms')->pluck('ads', 'term')->all();

        $this->assertSame(2, $terms['toyota']);
        $this->assertSame(2, $terms['toyota corolla']);
        $this->assertArrayNotHasKey('lamborghini', $terms); // not live
        $this->assertArrayNotHasKey('for', $terms); // stop word
        $this->assertArrayNotHasKey('2015', $terms); // a number alone is no help
        $this->assertArrayNotHasKey('2015 for', $terms); // phrase ending in a stop word
        $this->assertArrayNotHasKey('corolla clean', $terms); // a phrase in only one ad
        $this->assertSame(1, $terms['clean']);
    }

    public function test_rebuild_handles_accents_as_separate_terms(): void
    {
        $this->car('Cafe table');
        $this->car('Café chairs');

        SearchTerms::rebuild();

        $this->assertEqualsCanonicalizing(['cafe', 'café'], DB::table('search_terms')->where('term', 'like', 'caf%')->pluck('term')->all());
    }

    public function test_suggest_finds_starts_then_inner_words_and_escapes_wildcards(): void
    {
        DB::table('search_terms')->insert([
            ['term' => 'toyota', 'ads' => 9],
            ['term' => 'toyota corolla', 'ads' => 5],
            ['term' => 'corolla', 'ads' => 6],
            ['term' => 'old corolla', 'ads' => 1],
            ['term' => 'total', 'ads' => 2],
        ]);

        $this->assertSame(['toyota', 'toyota corolla', 'total'], SearchTerms::suggest('To'));
        $this->assertSame(['corolla', 'toyota corolla', 'old corolla'], SearchTerms::suggest('corolla'));
        $this->assertSame([], SearchTerms::suggest('t'));
        $this->assertSame([], SearchTerms::suggest('%%'));
        $this->assertSame([], SearchTerms::suggest('t_y'));
    }

    public function test_endpoint_gives_categories_terms_and_the_category_scope(): void
    {
        $this->car('Toyota Hilux');
        SearchTerms::rebuild();

        $res = $this->getJson('/search-suggest?q=toy&in=cars')->assertOk()
            ->assertHeader('Cache-Control', 'max-age=300, public');
        $res->assertJsonPath('terms.0.text', 'toyota');
        $res->assertJsonPath('terms.0.url', url('/search?q=toyota'));
        $res->assertJsonPath('scope.text', 'toy in Cars');
        $res->assertJsonPath('scope.url', url('/cars?q=toy'));
        $this->assertSame([], $res->json('categories')); // "Toys" matches but has no ads, so it is left out
        $res->assertCookieMissing(config('session.cookie'));
        $this->assertEmpty($res->headers->getCookies(), 'no cookie for a suggestion');

        $this->getJson('/search-suggest?q=car')->assertOk()
            ->assertJsonPath('categories.0.name', 'Cars')
            ->assertJsonPath('categories.0.url', url('/cars'))
            ->assertJsonPath('scope', null);
    }

    public function test_endpoint_speaks_somali_and_finds_somali_category_names(): void
    {
        $this->car('Toyota Hilux');
        $name = Category::where('slug', 'cars')->value('name');
        $so = is_array($name) ? ($name['so'] ?? null) : json_decode((string) $name, true)['so'] ?? null;
        $this->assertIsString($so);

        $res = $this->getJson('/so/search-suggest?q='.urlencode(mb_substr(mb_strtolower($so), 0, 3)).'&in=cars')->assertOk();
        $this->assertContains($so, array_column($res->json('categories'), 'name'));
        $this->assertStringStartsWith(url('/so/'), $res->json('categories.0.url'));
        $this->assertStringContainsString('gudaha', $res->json('scope.text'));
    }

    public function test_bad_input_is_harmless(): void
    {
        $this->getJson('/search-suggest')->assertOk()->assertExactJson(['terms' => [], 'categories' => [], 'scope' => null]);
        $this->getJson('/search-suggest?q=a')->assertOk()->assertJsonPath('terms', []);
        $this->getJson('/search-suggest?q[]=x')->assertOk();
        $this->getJson('/search-suggest?q=toy&in=../../etc')->assertOk()->assertJsonPath('scope', null);
        $this->getJson('/search-suggest?q='.str_repeat('z', 500))->assertOk();
        $this->getJson('/search-suggest?q=<script>')->assertOk()->assertJsonPath('terms', []);
    }

    public function test_switch_off_hides_the_endpoint_and_the_attributes(): void
    {
        $this->car('Toyota Hilux');
        app(FeatureManager::class)->set('search.suggestions', false);

        $this->getJson('/search-suggest?q=toy')->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('data-suggest=', false)->assertDontSee('id="suggest-text"', false);
        $this->get('/cars')->assertOk()->assertDontSee('data-suggest=', false);
    }

    public function test_header_search_shows_off_home_and_searches_inside_the_category(): void
    {
        $this->car('Toyota Hilux');

        $home = $this->get('/')->assertOk();
        $home->assertDontSee('id="hq-desktop"', false)->assertSee('data-suggest="'.url('/search-suggest').'"', false);

        $page = $this->get('/cars?q=hilux')->assertOk();
        $page->assertSee('id="hq-desktop"', false)->assertSee('id="hq-phone"', false)
            ->assertSee('action="'.url('/cars').'"', false)
            ->assertSee('placeholder="Search in Cars"', false)
            ->assertSee('data-scope="cars"', false)
            ->assertSee('value="hilux"', false)
            ->assertSee('id="suggest-text"', false);

        $this->get('/so/cars')->assertOk()->assertSee('data-suggest="'.url('/so/search-suggest').'"', false)
            ->assertSee(__('site.search.open', [], 'so'), false);

        $this->get('/search?q=abc')->assertOk()->assertSee('action="'.url('/search').'"', false)->assertSee('value="abc"', false);

        app(FeatureManager::class)->set('site.header_search', false);
        $this->get('/cars')->assertOk()->assertDontSee('id="hq-desktop"', false)->assertDontSee('id="hq-phone"', false);
    }

    public function test_header_search_escapes_what_was_typed(): void
    {
        $this->get('/search?q='.urlencode('"><script>alert(1)</script>'))->assertOk()
            ->assertDontSee('"><script>alert(1)</script>', false);
    }

    public function test_command_rebuilds_and_runs_every_night(): void
    {
        $this->car('Nissan Patrol');

        $this->artisan('iibsom:search-terms')->assertSuccessful();
        $this->assertTrue(DB::table('search_terms')->where('term', 'nissan')->exists());

        $events = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains((string) $e->command, 'iibsom:search-terms'));
        $this->assertCount(1, $events);
        $this->assertSame('50 3 * * *', $events->first()->expression);
    }
}
