<?php

namespace Tests\Feature;

use App\Support\Cache\PageCache;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use App\Support\Theme\SiteColors;
use Tests\Feature\Admin\AdminTestCase;

/** PR 88: the website colour chosen in Admin → Settings → Appearance. */
class SiteColorTest extends AdminTestCase
{
    private function home(): string
    {
        return (string) $this->get('/')->assertOk()->getContent();
    }

    public function test_the_iibsom_blue_gives_back_its_own_scale(): void
    {
        $p = SiteColors::palette(SiteColors::DEFAULT)['light'];
        $css = file_get_contents(resource_path('css/app.css'));
        foreach ($p as $step => $hex) {
            preg_match("/--color-brand-{$step}: (#[0-9a-f]{6})/", (string) $css, $m);
            $this->assertNotEmpty($m, "brand-{$step} in app.css");
            // Within a few steps of each channel: the method copies the blue's lightness steps.
            foreach ([1, 3, 5] as $i) {
                $this->assertLessThanOrEqual(6, abs(hexdec(substr($hex, $i, 2)) - hexdec(substr($m[1], $i, 2))), "brand-{$step}: {$hex} vs {$m[1]}");
            }
        }
        $this->assertSame('', SiteColors::css(SiteColors::DEFAULT), 'the blue needs no extra style');
    }

    public function test_only_readable_colours_are_usable(): void
    {
        $this->assertTrue(SiteColors::readable('#0f7a3d'));
        $this->assertFalse(SiteColors::readable('#22c55e')); // light green: white text hard to read
        $this->assertFalse(SiteColors::readable('#ffff00'));
        $this->assertFalse(SiteColors::readable('red'));
        $this->assertFalse(SiteColors::readable('#12345'));

        $better = SiteColors::suggest('#22c55e');
        $this->assertNotNull($better);
        $this->assertTrue(SiteColors::readable($better));
        $this->assertGreaterThanOrEqual(4.5, SiteColors::contrastWithWhite($better));

        // Every shade is a real colour and the picked one is used as it is.
        $p = SiteColors::palette('#7c3aed');
        $this->assertSame('#7c3aed', $p['light'][700]);
        foreach ([...$p['light'], ...$p['dark'], $p['dark_text']] as $hex) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $hex);
        }
        // Light tints stay light, dark shades stay dark.
        $this->assertGreaterThan(SiteColors::contrast($p['light'][300], '#000000'), SiteColors::contrast($p['light'][50], '#000000'));
        $this->assertGreaterThan(SiteColors::contrast($p['light'][800], '#ffffff'), SiteColors::contrast($p['light'][950], '#ffffff'));
        // Dark mode text is readable on the dark page.
        $this->assertGreaterThanOrEqual(4.5, SiteColors::contrast($p['dark_text'], '#1c1916'));
    }

    public function test_site_uses_the_saved_colour_in_light_and_dark(): void
    {
        $html = $this->home();
        $this->assertStringNotContainsString('data-site-color', $html);
        $this->assertStringContainsString('<meta name="theme-color" content="#1d6bca">', $html);

        app(SettingsManager::class)->set('site.brand_color', '#0f7a3d');
        $html = $this->home();
        $this->assertStringContainsString('<style data-site-color>:root{--color-brand-50:', $html);
        $this->assertStringContainsString('--color-brand-700:#0f7a3d;', $html);
        $this->assertStringContainsString(":root[data-theme='dark']{--color-brand-50:", $html);
        $this->assertStringContainsString(":root[data-theme='dark'] :is(.text-brand-700,", $html);
        $this->assertStringContainsString('<meta name="theme-color" content="#0f7a3d">', $html);
        // After the main style sheet, so it wins.
        $this->assertGreaterThan(strpos($html, 'rel="stylesheet"'), strpos($html, 'data-site-color'));

        // Switch OFF: the blue again.
        app(FeatureManager::class)->set('site.custom_color', false);
        PageCache::bump();
        $html = $this->home();
        $this->assertStringNotContainsString('data-site-color', $html);
        $this->assertStringContainsString('<meta name="theme-color" content="#1d6bca">', $html);
    }

    public function test_a_bad_saved_value_never_reaches_the_page(): void
    {
        app(SettingsManager::class)->set('site.brand_color', '#ffff00</style><script>alert(1)</script>');
        $html = $this->home();
        $this->assertStringNotContainsString('data-site-color', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringContainsString('<meta name="theme-color" content="#1d6bca">', $html);
    }

    public function test_admin_checks_saves_and_refreshes_the_cached_site(): void
    {
        app(FeatureManager::class)->set('performance.page_cache', true);
        $this->home();
        $this->get('/')->assertHeader('X-Page-Cache', 'HIT');

        $this->asStaff();
        $this->getJson('/admin/api/settings/site-color')->assertOk()->assertJson(['color' => '#1d6bca', 'readable' => true, 'default' => '#1d6bca']);

        $check = $this->postJson('/admin/api/settings/site-color/check', ['color' => '#22C55E'])->assertOk();
        $check->assertJson(['color' => '#22c55e', 'readable' => false]);
        $this->assertNotNull($check->json('suggestion'));
        $this->assertCount(9, $check->json('palette'));

        $this->putJson('/admin/api/settings/site-color', ['color' => '#22c55e'])->assertUnprocessable()->assertJsonValidationErrors('color');
        $this->putJson('/admin/api/settings/site-color', ['color' => 'green'])->assertUnprocessable();
        $this->assertSame('#1d6bca', setting('site.brand_color'));

        $this->putJson('/admin/api/settings/site-color', ['color' => '#0F7A3D'])->assertOk()->assertJson(['color' => '#0f7a3d']);
        $this->assertSame('#0f7a3d', setting('site.brand_color'));
        $this->assertDatabaseHas('admin_activities', ['action' => 'settings.site_color', 'summary' => 'Website colour changed from #1d6bca to #0f7a3d']);

        auth()->logout();
        $this->assertStringContainsString('--color-brand-700:#0f7a3d;', $this->home(), 'guests see it at once (cache refreshed)');
    }

    public function test_only_staff_who_manage_settings_and_switch_off_closes_it(): void
    {
        $this->asStaff('moderator');
        $this->getJson('/admin/api/settings/site-color')->assertForbidden();
        $this->putJson('/admin/api/settings/site-color', ['color' => '#0f7a3d'])->assertForbidden();

        $this->asStaff();
        app(FeatureManager::class)->set('site.custom_color', false);
        $this->getJson('/admin/api/settings/site-color')->assertNotFound();
    }
}
