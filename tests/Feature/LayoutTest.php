<?php

namespace Tests\Feature;

use App\Support\Features\FeatureManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_has_mobile_viewport_header_and_footer(): void
    {
        $this->get('/')
            ->assertSee('name="viewport" content="width=device-width, initial-scale=1"', false)
            ->assertSee('<header', false)
            ->assertSee('<footer', false)
            ->assertSee('Skip to content')
            ->assertSee('Safety tips');
    }

    public function test_sell_button_follows_its_switch(): void
    {
        $this->get('/')->assertSee('btn-sell', false)->assertSee('Post an ad');

        app(FeatureManager::class)->set('ads.posting', false);
        $this->get('/')->assertDontSee('btn-sell', false)->assertDontSee('Post an ad')->assertDontSee('>Post ad<', false);
    }

    public function test_phone_bottom_bar_has_home_sell_and_profile(): void
    {
        $html = $this->get('/')->assertSee('aria-label="Bottom"', false)->getContent();
        $bar = substr($html, strpos($html, 'aria-label="Bottom"'));

        $this->assertStringContainsString('>Home<', $bar);
        $this->assertStringContainsString('>Sell<', $bar);
        $this->assertStringContainsString('>Profile<', $bar);
        $this->assertStringContainsString('aria-current="page"', $bar);
        $this->assertStringNotContainsString('>Saved ads<', $bar);
        $this->assertStringNotContainsString('>Messages<', $bar);
    }

    public function test_bottom_bar_items_follow_switches(): void
    {
        app(FeatureManager::class)->set('favourites', true);
        app(FeatureManager::class)->set('login.google', false);

        $html = $this->get('/')->getContent();
        $bar = substr($html, strpos($html, 'aria-label="Bottom"'));

        $this->assertStringContainsString('>Saved<', $bar);
        $this->assertStringNotContainsString('>Profile<', $bar);
    }

    public function test_phase_2_header_icons_are_hidden_until_switched_on(): void
    {
        $this->get('/')->assertDontSee('Saved ads')->assertDontSee('Messages');

        app(FeatureManager::class)->set('chat', true);
        $this->get('/')->assertSee('Messages');
    }

    public function test_page_has_title_and_description_for_google(): void
    {
        $this->get('/')
            ->assertSee('<title>Buy and sell in Somalia · iibsom</title>', false)
            ->assertSee('<meta name="description" content="Buy and sell cars', false);
    }

    public function test_no_web_fonts_are_loaded(): void
    {
        $this->get('/')->assertDontSee('fonts.bunny.net', false)->assertDontSee('fonts.googleapis.com', false);
    }
}
