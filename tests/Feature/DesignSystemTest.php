<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** PR 74: one design system for the visitor side (dark mode, cards, empty states, friendly errors). */
class DesignSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
    }

    private function car(string $title, array $attributes = []): Ad
    {
        return Ad::factory()->live()->create($attributes + [
            'title' => $title,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
            'published_at' => now(),
        ]);
    }

    // ---- Dark mode ----

    public function test_dark_mode_switch_on_lets_the_page_follow_the_device_and_shows_the_switch(): void
    {
        foreach (['/', '/so'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('data-theme="light"', $html, $url);
            $this->assertStringContainsString("matchMedia('(prefers-color-scheme: dark)')", $html, $url);
            $this->assertStringContainsString('data-theme-switch', $html, $url);
        }
        $this->get('/')->assertSee(['Auto', 'Light', 'Dark']);
        $this->get('/so')->assertSee(__('site.theme.dark', [], 'so'));
    }

    public function test_dark_mode_switch_off_keeps_the_site_light(): void
    {
        app(FeatureManager::class)->set('site.dark_mode', false);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('data-theme="light"', $html);
        $this->assertStringNotContainsString('data-theme-switch', $html);
        $this->assertStringNotContainsString('prefers-color-scheme', $html);
    }

    public function test_the_stand_alone_error_page_has_a_dark_look_too(): void
    {
        $html = view('errors._plain', ['key' => 'server'])->render();
        $this->assertStringContainsString('prefers-color-scheme:dark', $html);
        $this->assertStringContainsString('<svg', $html);
    }

    public function test_one_button_style_no_capital_letters_on_the_sell_button(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));
        $this->assertDoesNotMatchRegularExpression('/\.btn-sell \{[^}]*uppercase/', $css);
        $this->assertStringContainsString(':focus-visible', $css);
        $this->assertStringContainsString('.skeleton-card', $css);
    }

    // ---- Cards ----

    public function test_cards_keep_two_title_lines_and_a_calm_no_photo_box(): void
    {
        $this->car('Toyota Vitz 2012 without photos');

        $this->get('/cars')->assertOk()
            ->assertSee('data-card-title', false)
            ->assertSee('min-h-[2lh]', false)
            ->assertSee('data-no-photo', false)
            ->assertSee('No photo');
        $this->get('/so/cars')->assertSee(__('ads.page.no_photo', [], 'so'));
    }

    public function test_sub_category_chips_wrap_on_a_computer_instead_of_being_cut_off(): void
    {
        $this->get('/cars')->assertOk()->assertSee('lg:flex-wrap', false)->assertSee('lg:overflow-visible', false);
    }

    // ---- Empty states ----

    public function test_no_results_shows_the_drawing_and_a_clear_filters_link(): void
    {
        $box = $this->emptyBox($this->get('/cars?q=zzqqxx-nothing')->assertOk()->getContent());
        $this->assertStringContainsString('Clear filters', $box);
        $this->assertStringContainsString('href="'.url('/cars').'"', $box);
        $this->assertStringContainsString('Post an ad', $box);

        $this->assertStringContainsString(__('ads.browse.clear', [], 'so'), $this->emptyBox($this->get('/so/cars?q=zzqqxx-nothing')->getContent()));
    }

    public function test_no_results_without_filters_has_no_clear_link(): void
    {
        Ad::query()->delete();
        $this->assertStringNotContainsString('Clear filters', $this->emptyBox($this->get('/cars')->assertOk()->getContent()));
    }

    /** The "nothing here" box only (the filter panel has its own "Clear filters"). */
    private function emptyBox(string $html): string
    {
        $start = strpos($html, 'data-empty-state');
        $this->assertNotFalse($start, 'no empty-state box');

        return substr($html, $start, (int) strpos($html, '</div>', (int) strpos($html, 'mt-5 flex', $start)) - $start);
    }

    public function test_empty_saved_searches_and_following_pages_have_one_action(): void
    {
        $user = User::factory()->completed()->create();
        foreach (['favourites', 'saved_searches'] as $switch) {
            app(FeatureManager::class)->set($switch, true);
        }
        foreach (['/account/saved', '/account/searches', '/account/following'] as $url) {
            $this->actingAs($user)->get($url)->assertOk()->assertSee('data-empty-state', false)->assertSee('All ads');
        }
    }

    public function test_empty_my_ads_offers_to_post_an_ad(): void
    {
        $user = User::factory()->completed()->create();
        $this->actingAs($user)->get('/account/ads')->assertOk()->assertSee('data-empty-state', false)->assertSee('Post an ad');
    }

    public function test_next_free_move_to_top_is_a_quiet_line_not_a_button(): void
    {
        $user = User::factory()->completed()->create();
        $ad = $this->car('Toyota Vitz 2012 bumped', ['user_id' => $user->id]);
        $ad->forceFill(['bumped_at' => now()])->save();

        $html = $this->actingAs($user)->get('/account/ads')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<p class="[^"]*text-xs[^"]*" data-bump-next>/', $html);
    }

    // ---- Friendly 404 ----

    public function test_404_has_a_drawing_a_search_box_and_a_way_home_in_both_languages(): void
    {
        $this->get('/no-such-page-here')->assertNotFound()
            ->assertSee('data-empty-state', false)->assertSee('role="search"', false)->assertSee('Back to home');
        $this->get('/so/no-such-page-here')->assertNotFound()->assertSee(__('site.errors.back_home', [], 'so'));
    }

    // ---- Phone bottom bar ----

    public function test_bottom_bar_saved_label_is_one_short_word(): void
    {
        app(FeatureManager::class)->set('favourites', true);
        $this->get('/')->assertOk()->assertSee('<span>Saved</span>', false);
        $this->get('/so')->assertOk()->assertSee('<span>Kaydsan</span>', false)->assertDontSee('<span>Xayeysiisyada la keydiyey</span>', false);
    }

    // ---- The audit ----

    public function test_the_audit_has_a_score_table_and_every_picture_it_shows_exists(): void
    {
        $doc = (string) file_get_contents(base_path('docs/ux-audit.md'));
        $this->assertStringContainsString('| Page | What feels off | What is missing | Before (L/E/T) | After (L/E/T) | PR |', $doc);
        preg_match_all('/\]\((ux-audit\/[^)]+)\)/', $doc, $m);
        $this->assertGreaterThanOrEqual(20, count($m[1]));
        foreach ($m[1] as $picture) {
            $this->assertFileExists(base_path('docs/'.$picture));
        }
    }
}
