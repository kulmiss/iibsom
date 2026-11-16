<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\User;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SomaliTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
    }

    public function test_every_english_text_has_a_somali_text(): void
    {
        foreach (File::files(lang_path('en')) as $file) {
            $name = $file->getFilenameWithoutExtension();
            $en = array_keys(Arr::dot(require $file->getPathname()));
            $so = is_file(lang_path("so/{$name}.php")) ? array_keys(Arr::dot(require lang_path("so/{$name}.php"))) : [];

            $this->assertSame([], array_values(array_diff($en, $so)), "lang/so/{$name}.php is missing these keys");
        }
    }

    public function test_somali_pages_use_somali_texts_and_names(): void
    {
        $this->get('/so')->assertOk()->assertSee('Maxaad raadinaysaa?')->assertSee('Baabuur');
        $this->get('/so/cars')->assertOk()->assertSee('Baabuur');
        $this->get('/so/safety')->assertOk()->assertSee('Ammaan ahaw')->assertSee('Weligaa lacag hore ha dirin', false);
        $this->get('/cars')->assertOk()->assertSee('Cars')->assertDontSee('Baabuur');
    }

    public function test_form_errors_are_in_somali(): void
    {
        $user = User::factory()->completed()->create(['city_id' => City::where('slug', 'mogadishu')->value('id')]);

        $this->actingAs($user)->from('/so/post/cars')->post('/so/post/cars', [])
            ->assertSessionHasErrors(['title' => 'Cinwaan waa loo baahan yahay.']);
    }

    public function test_dates_follow_the_language(): void
    {
        app()->setLocale('so');
        $this->assertNotSame('1 day ago', now()->subDay()->diffForHumans());
    }
}
