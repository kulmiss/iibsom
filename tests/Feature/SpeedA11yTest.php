<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\AdPhoto;
use App\Models\Category;
use App\Models\User;
use App\Support\Cache\PageCache;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** PR 80: what the speed and accessibility check found, kept from coming back. */
class SpeedA11yTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function carWithPhotos(int $photos, string $title = 'Toyota Vitz'): Ad
    {
        $ad = Ad::factory()->live()->create(['title' => $title, 'category_id' => Category::where('slug', 'cars')->value('id'), 'published_at' => now()]);
        foreach (range(1, $photos) as $i) {
            AdPhoto::factory()->create(['ad_id' => $ad->id, 'user_id' => $ad->user_id, 'sort' => $i]);
        }
        PageCache::bump();

        return $ad;
    }

    public function test_the_page_knows_about_javascript_before_it_draws(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString("document.documentElement.classList.add('js')", $html);
        // ...and that little script has the page's nonce, so the strict CSP allows it.
        $this->assertMatchesRegularExpression('/<script nonce="[^"]+">document\.documentElement\.classList\.add\(\'js\'\)/', $html);
    }

    public function test_first_two_cards_of_a_list_load_at_once_the_rest_later(): void
    {
        foreach (range(1, 4) as $i) {
            $this->carWithPhotos(1, 'Car '.$i);
        }
        $html = $this->get('/cars')->assertOk()->getContent();
        $grid = substr($html, (int) strpos($html, 'data-results'));

        $this->assertSame(2, substr_count($grid, 'fetchpriority="high"'));
        $this->assertGreaterThanOrEqual(2, substr_count($grid, 'loading="lazy"'));
        // Page 2 is not the first screen: all wait.
        $this->assertStringNotContainsString('fetchpriority="high"', substr((string) $this->get('/cars?page=2')->getContent(), (int) strpos((string) $this->get('/cars?page=2')->getContent(), '<main')));
    }

    public function test_ad_page_main_photo_first_the_others_later(): void
    {
        $ad = $this->carWithPhotos(3);
        $html = $this->get($ad->url())->assertOk()->getContent();
        $gallery = substr($html, (int) strpos($html, 'data-gallery'), 6000);

        $this->assertSame(1, substr_count($gallery, 'fetchpriority="high"'));
        $this->assertGreaterThanOrEqual(2, substr_count($gallery, 'fetchpriority="low"'));
        // The first photo is drawn at once (no "decode later" on the biggest thing on the page).
        $this->assertDoesNotMatchRegularExpression('/fetchpriority="high"[^>]*decoding="async"/', $gallery);
    }

    public function test_dark_mode_keeps_readable_text_on_yellow_and_blue_links(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));
        $this->assertMatchesRegularExpression("/\\[data-theme='dark'\\] :is\\(\\.btn-sell[^)]*\\) \\{ color: #1c1917; \\}/", $css);
        $this->assertStringContainsString('.prose-iibsom a) { color: #8ab8f2; }', $css);
        $this->assertStringContainsString('--color-brand-700: #1d6bca;', $css);
    }

    public function test_shared_hosting_compresses_pages(): void
    {
        $htaccess = (string) file_get_contents(public_path('.htaccess'));
        $this->assertStringContainsString('<IfModule mod_deflate.c>', $htaccess);
        $this->assertStringContainsString('DEFLATE text/html', $htaccess);
    }

    public function test_language_button_name_starts_with_what_people_see(): void
    {
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->actingAs(User::factory()->completed()->create())->get('/')->assertSee('<span>EN</span><span class="sr-only"> – Language: English</span>', false);
    }
}
