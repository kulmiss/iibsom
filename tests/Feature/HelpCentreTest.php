<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Help\HelpCentre;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class HelpCentreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
    }

    public function test_help_page_lists_the_articles_in_order(): void
    {
        $res = $this->get('/help')->assertOk()->assertSee('How can we help?');
        $res->assertSeeInOrder(['How to post an ad', 'Good photos sell faster', 'Why is my ad waiting?', 'Buy safely', 'Your account']);
        $this->assertSame([], $res->headers->getCookies(), 'Guest help pages stay cookie-free (cacheable).');

        $this->get('/so/help')->assertOk()->assertSee('Sideen kuu caawin karnaa?')->assertSee('Sida loo dhigo xayeysiis');
    }

    public function test_article_uses_the_real_limits_from_settings(): void
    {
        app(SettingsManager::class)->set('ads.max_photos', 6);
        app(SettingsManager::class)->set('ads.max_per_day', 3);

        $this->get('/help/post-an-ad')->assertOk()
            ->assertSee('<h1', false)->assertSee('How to post an ad')
            ->assertSee('Add up to 6 photos.')
            ->assertSee('You can post up to 3 ads a day.')
            ->assertSee('More help');
        $this->get('/so/help/post-an-ad')->assertOk()->assertSee('Ku dar ilaa 6 sawir.');
        $this->get('/help/my-ads')->assertOk()->assertSee('ads end after 60 days. <strong>Renew</strong> shows 7 days before the end', false)->assertSee('<strong>Account</strong> → <strong>My ads</strong>', false);
    }

    public function test_links_inside_articles_go_to_the_right_language(): void
    {
        $this->get('/help/buy-safely')->assertOk()->assertSee('href="'.url('/safety').'"', false);
        $this->get('/so/help/buy-safely')->assertOk()->assertSee('href="'.url('/so/safety').'"', false);
        $this->get('/so/help/your-account')->assertOk()->assertSee('href="'.url('/so/help').'"', false);
    }

    public function test_no_article_keeps_an_unfilled_word(): void
    {
        foreach (['en', 'so'] as $locale) {
            app()->setLocale($locale);
            foreach (HelpCentre::articles($locale) as $item) {
                $html = (string) HelpCentre::article($locale, $item['slug'])['html'];
                $this->assertDoesNotMatchRegularExpression('/:[a-z_]{3,}/', strip_tags($html), "{$locale}/{$item['slug']} has a word that was not filled in");
                $this->assertNotSame('', $item['title']);
            }
        }
    }

    public function test_every_article_exists_in_english_and_somali(): void
    {
        $names = fn (string $locale) => array_map(fn ($f) => $f->getFilename(), File::files(resource_path("help/{$locale}")));

        $this->assertSame($names('en'), $names('so'));
        $this->assertCount(10, $names('en')); // + How to sell (PR 55), Posting rules + How to buy (PR 69)
        foreach (HelpCentre::raw('so') as $slug => $article) {
            $this->assertNotSame(HelpCentre::raw('en')[$slug]['title'], $article['title'], "help/so/{$slug} is still in English");
        }
    }

    public function test_unknown_article_is_404(): void
    {
        $this->get('/help/nothing-here')->assertNotFound();
        $this->get('/help/../../.env')->assertNotFound();
        $this->get('/help/POST-AN-AD')->assertNotFound();
    }

    public function test_help_email_box_shows_only_when_set(): void
    {
        $this->get('/help')->assertOk()->assertDontSee('Still need help?');

        app(SettingsManager::class)->set('site.support_email', 'help@iibsom.test');

        $this->get('/help')->assertOk()->assertSee('Still need help?')->assertSee('mailto:help@iibsom.test', false);
        $this->get('/so/help/your-account')->assertOk()->assertSee('Weli caawimaad ma u baahan tahay?');
    }

    public function test_links_in_footer_account_and_sitemap(): void
    {
        $this->get('/')->assertOk()->assertSee('href="'.url('/help').'"', false);
        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/help'), false)->assertSee(url('/help/post-an-ad'), false);

        $user = User::factory()->completed()->create();
        $this->actingAs($user)->get('/account')->assertOk()->assertSee('href="'.url('/help').'"', false);
    }

    public function test_switch_off_hides_help_everywhere(): void
    {
        app(FeatureManager::class)->set('help.centre', false);

        $this->get('/help')->assertNotFound();
        $this->get('/so/help/post-an-ad')->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('href="'.url('/help').'"', false);
        $this->get('/sitemap.xml')->assertOk()->assertDontSee(url('/help'), false);
    }
}
