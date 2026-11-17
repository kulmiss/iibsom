<?php

namespace Tests\Feature\Admin;

use App\Support\Cache\PageCache;

class FooterSettingsTest extends AdminTestCase
{
    /** @var array<string, mixed> */
    private array $base = ['ads_expiry_days' => 60, 'ads_max_per_day' => 5, 'ads_max_photos' => 8, 'languages' => ['en', 'so']];

    public function test_social_links_and_footer_links_are_saved_and_shown(): void
    {
        $this->asStaff();
        $this->getJson('/admin/api/settings/general')->assertOk()
            ->assertJsonPath('footer_social.facebook', '')
            ->assertJsonPath('footer_networks.tiktok', 'TikTok')
            ->assertJsonPath('footer_link_options.cookies', 'Cookie info');

        $version = PageCache::version();
        $this->putJson('/admin/api/settings/general', $this->base + [
            'footer_social' => ['facebook' => ' https://www.facebook.com/iibsom ', 'telegram' => 'https://t.me/iibsom', 'whatsapp' => ''],
            'footer_links' => ['privacy', 'about', 'popular'],
        ])->assertOk()
            ->assertJsonPath('footer_social.facebook', 'https://www.facebook.com/iibsom')
            ->assertJsonPath('footer_social.instagram', '')
            ->assertJsonPath('footer_links', ['about', 'privacy', 'popular']); // kept in the list order
        $this->assertNotSame($version, PageCache::version(), 'Cached pages show the new footer at once.');

        auth()->logout();
        $html = (string) $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('href="https://www.facebook.com/iibsom" rel="noopener me" target="_blank"', $html);
        $this->assertStringContainsString('href="https://t.me/iibsom"', $html);
        $this->assertStringNotContainsString('<span class="sr-only">Instagram</span>', $html, 'Empty = hidden.');
        $this->assertStringNotContainsString('>Terms</a>', $html, 'Turned off.');
        $this->assertStringNotContainsString('Cookie info', $html);
        $this->assertStringContainsString('>Privacy</a>', $html);
    }

    public function test_only_https_links_on_the_networks_own_site(): void
    {
        $this->asStaff();
        foreach ([
            'http://facebook.com/iibsom',                 // not https
            'https://facebook.com.evil.test/iibsom',      // look-alike
            'https://evilfacebook.com/x',                 // look-alike
            'javascript:alert(1)',
            'https://example.com/iibsom',
        ] as $bad) {
            $this->putJson('/admin/api/settings/general', $this->base + ['footer_social' => ['facebook' => $bad]])
                ->assertUnprocessable()->assertJsonValidationErrors('footer_social.facebook');
        }
        $this->putJson('/admin/api/settings/general', $this->base + ['footer_social' => ['myspace' => 'https://myspace.com/x']])->assertUnprocessable();
        $this->putJson('/admin/api/settings/general', $this->base + ['footer_links' => ['about', 'casino']])->assertUnprocessable();
        // The setup wizard cannot slip one in either.
        $this->postJson('/admin/api/setup', $this->base + ['cities' => [], 'demo_data' => false, 'footer_social' => ['facebook' => 'https://evil.test']])->assertUnprocessable();
        $this->putJson('/admin/api/settings/general', $this->base + ['footer_social' => ['facebook' => 'https://m.facebook.com/iibsom', 'youtube' => 'https://youtu.be/abc', 'whatsapp' => 'https://wa.me/252611234567']])->assertOk();
    }

    public function test_moderator_cannot_change_the_footer(): void
    {
        $this->asStaff('moderator');
        $this->putJson('/admin/api/settings/general', $this->base + ['footer_social' => ['facebook' => 'https://facebook.com/x']])->assertForbidden();
    }
}
