<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Support\Cache\PageCache;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use App\Support\Site\Footer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FooterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
    }

    public function test_big_footer_columns_languages_and_year(): void
    {
        app(FeatureManager::class)->set('site.contact_form', false); // the help-email link (form: end of this test)
        $html = (string) $this->get('/')->assertOk()->getContent();
        foreach (['data-site-footer', 'data-footer-section', '>About</summary>' => false, 'About us', 'Terms', 'Privacy', 'Cookie info', 'Copyright &amp; problems', 'Safety tips', 'Help centre &amp; FAQ', '© '.now()->year] as $k => $v) {
            $needle = is_string($k) ? $k : $v;
            if ($v === false) {
                $this->assertMatchesRegularExpression('#About\s*<svg#', $html);

                continue;
            }
            $this->assertStringContainsString($needle, $html, $needle);
        }
        $this->assertMatchesRegularExpression('#>\s*Popular\s*<svg#', $html);
        // Columns are sent open (no JavaScript = everything visible); footer.js folds them on phones.
        $this->assertStringContainsString('<details open class="group border-t', $html);
        // Languages: each in its own name, instant switch, current one marked.
        $this->assertMatchesRegularExpression('#data-lang-switch="so"[^>]*>Soomaali</a>#', $html);
        $this->assertMatchesRegularExpression('#aria-current="true"\s*>English</a>#', $html);
        // The coast picture is decorative.
        $this->assertStringContainsString('preserveAspectRatio="xMidYMax slice"', $html);
        // No help email yet: no "Contact us"; no social pages yet: no "Follow us".
        $this->assertStringNotContainsString('Contact us', $html);
        $this->assertStringNotContainsString('Follow us', $html);

        app(SettingsManager::class)->set('site.support_email', 'help@iibsom.test');
        $this->get('/so')->assertOk()->assertSee('href="mailto:help@iibsom.test"', false)->assertSee('Nala soo xiriir')->assertSee('Macluumaadka kukiyada');

        // With the contact form ON (PR 69) "Contact us" opens the form, even without a help email.
        app(FeatureManager::class)->set('site.contact_form', true);
        app(SettingsManager::class)->set('site.support_email', '');
        PageCache::bump();
        $this->get('/')->assertOk()->assertSee('href="'.url('/contact').'"', false)->assertSee('Contact us');
    }

    public function test_popular_links_follow_live_ads(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('href="'.url('/search?city=mogadishu').'"', $html);
        app(SettingsManager::class)->set('footer.links', ['about']);
        $html = (string) $this->get('/')->assertOk()->assertDontSee('href="'.url('/search?city=mogadishu').'"', false)->getContent();
        $this->assertDoesNotMatchRegularExpression('#>\s*Popular\s*<svg#', $html);
    }

    public function test_every_network_has_its_icon(): void
    {
        foreach (array_keys(Footer::NETWORKS) as $key) {
            $this->assertFileExists(resource_path("svg/icons/social-{$key}.svg"));
        }
    }

    public function test_a_bad_social_link_in_the_database_is_never_shown(): void
    {
        app(SettingsManager::class)->set('footer.social', ['facebook' => 'https://evil.test/phish', 'instagram' => 'https://instagram.com/iibsom']);
        $this->get('/')->assertOk()->assertDontSee('evil.test')->assertSee('https://instagram.com/iibsom', false)->assertSee('Follow us');
    }

    public function test_new_pages_in_both_languages(): void
    {
        $this->get('/cookies')->assertOk()->assertSee('Cookie info')->assertSee('iibsom-session');
        $this->get('/so/cookies')->assertOk()->assertSee('Macluumaadka kukiyada');
        $this->get('/copyright')->assertOk()->assertSee('Report this ad');
        $this->get('/so/copyright')->assertOk()->assertSee('Ka warbixi xayeysiiskan');
    }

    public function test_upgrade_adds_the_pages_and_keeps_edited_ones(): void
    {
        Page::whereIn('slug', ['cookies', 'copyright'])->delete();
        Page::where('slug', 'about')->firstOrFail()->forceFill(['title' => ['en' => 'Our story', 'so' => 'Sheekadayada']])->save();
        (require database_path('migrations/2026_11_17_270000_footer_pages.php'))->up();
        $this->assertTrue(Page::where('slug', 'cookies')->exists());
        $this->assertSame('Our story', Page::where('slug', 'about')->firstOrFail()->title['en']);
    }

    public function test_switch_off_shows_the_short_footer(): void
    {
        app(FeatureManager::class)->set('site.new_footer', false);
        $this->get('/')->assertOk()->assertSee('data-site-footer', false)->assertDontSee('data-footer-section', false)->assertSee('About us');
    }
}
