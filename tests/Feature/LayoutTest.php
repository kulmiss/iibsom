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
        $this->get('/')->assertSee('class="btn-sell"', false)->assertSee('Post an ad');

        app(FeatureManager::class)->set('ads.posting', false);
        $this->get('/')->assertDontSee('btn-sell', false)->assertDontSee('Post an ad');
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
            ->assertSee('<title>Buy and sell in Somalia · Iibsom</title>', false)
            ->assertSee('<meta name="description" content="Buy and sell cars', false);
    }

    public function test_no_web_fonts_are_loaded(): void
    {
        $this->get('/')->assertDontSee('fonts.bunny.net', false)->assertDontSee('fonts.googleapis.com', false);
    }
}
