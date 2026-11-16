<?php

namespace Tests\Feature;

use App\Support\Features\FeatureManager;
use App\Support\Localization\Locales;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    private function enableSomali(): void
    {
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        app()->forgetScopedInstances();
    }

    public function test_home_is_english_by_default(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="en"', false)
            ->assertSee('What are you looking for?')
            ->assertSee('hreflang="en"', false);
    }

    public function test_only_english_is_on_at_launch(): void
    {
        $this->assertSame(['en'], app(Locales::class)->enabled());
    }

    public function test_switcher_is_hidden_with_one_language(): void
    {
        $this->get('/')->assertDontSee('data-lang-switch', false);
    }

    public function test_somali_url_is_404_while_somali_is_off(): void
    {
        $this->get('/so')->assertNotFound()->assertSee('Page not found');
    }

    public function test_default_language_has_no_prefix_url(): void
    {
        $this->get('/en')->assertNotFound();
    }

    public function test_somali_works_when_turned_on_and_falls_back_to_english(): void
    {
        $this->enableSomali();

        $this->get('/so')
            ->assertOk()
            ->assertSee('<html lang="so"', false)
            ->assertSee('What are you looking for?') // no Somali text yet → English
            ->assertSee('<link rel="canonical" href="'.url('/so').'">', false);
    }

    public function test_switcher_and_hreflang_show_when_two_languages_are_on(): void
    {
        $this->enableSomali();

        $this->get('/')
            ->assertSee('data-lang-switch="so"', false)
            ->assertSee('Soomaali')
            ->assertSee('hreflang="so" href="'.url('/so').'"', false)
            ->assertSee('hreflang="x-default" href="'.url('/').'"', false);
    }

    public function test_language_switch_feature_off_hides_switcher(): void
    {
        $this->enableSomali();
        app(FeatureManager::class)->set('site.language_switch', false);

        $this->get('/')->assertDontSee('data-lang-switch', false);
    }

    public function test_links_stay_in_the_current_language(): void
    {
        $this->enableSomali();

        $this->get('/so')->assertSee('href="'.url('/so').'"', false);
    }

    public function test_url_for_other_language_keeps_path_and_query(): void
    {
        $locales = app(Locales::class);

        $request = Request::create('/so/cars/mogadishu?sort=cheap');
        $this->assertSame(url('/cars/mogadishu').'?sort=cheap', $locales->urlFor('en', $request));

        $request = Request::create('/cars');
        $this->assertSame(url('/so/cars'), $locales->urlFor('so', $request));
    }

    public function test_every_english_text_is_filled_in(): void
    {
        $texts = Arr::dot(require lang_path('en/site.php'));

        foreach ($texts as $key => $text) {
            $this->assertNotSame('', trim($text), "site.{$key} is empty");
        }
    }

    public function test_somali_file_uses_only_keys_that_exist_in_english(): void
    {
        $en = array_keys(Arr::dot(require lang_path('en/site.php')));
        $so = array_keys(Arr::dot(require lang_path('so/site.php')));

        $this->assertSame([], array_values(array_diff($so, $en)));
    }
}
