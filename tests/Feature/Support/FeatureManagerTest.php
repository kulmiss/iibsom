<?php

namespace Tests\Feature\Support;

use App\Support\Features\FeatureManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use Tests\TestCase;

class FeatureManagerTest extends TestCase
{
    use RefreshDatabase;

    private function features(): FeatureManager
    {
        return app(FeatureManager::class);
    }

    public function test_defaults_come_from_config(): void
    {
        $this->assertTrue($this->features()->enabled('login.google'));
        $this->assertFalse($this->features()->enabled('login.phone'));
    }

    public function test_every_phase_2_and_3_feature_is_off_by_default(): void
    {
        foreach (config('features') as $key => $definition) {
            if (in_array($definition['group'], ['Phase 2', 'Phase 3'], true)) {
                $this->assertFalse($this->features()->enabled($key), "{$key} must be OFF by default");
            }
        }
    }

    public function test_every_feature_has_label_description_and_group(): void
    {
        foreach (config('features') as $key => $definition) {
            $this->assertNotEmpty($definition['label'] ?? null, "{$key} label");
            $this->assertNotEmpty($definition['description'] ?? null, "{$key} description");
            $this->assertNotEmpty($definition['group'] ?? null, "{$key} group");
            $this->assertIsBool($definition['default'] ?? null, "{$key} default");
        }
    }

    public function test_admin_change_wins_over_default_and_is_saved(): void
    {
        $this->features()->set('login.google', false);

        $this->assertFalse($this->features()->enabled('login.google'));
        $this->assertDatabaseHas('features', ['key' => 'login.google', 'enabled' => false]);

        // A new request (fresh scoped instance) still sees the change via cache/DB.
        app()->forgetScopedInstances();
        $this->assertFalse(app(FeatureManager::class)->enabled('login.google'));
    }

    public function test_reset_goes_back_to_default(): void
    {
        $this->features()->set('login.phone', true);
        $this->assertTrue($this->features()->enabled('login.phone'));

        $this->features()->reset('login.phone');
        $this->assertFalse($this->features()->enabled('login.phone'));
    }

    public function test_changing_a_switch_clears_the_cache(): void
    {
        $this->assertTrue($this->features()->enabled('contact.call'));
        $this->features()->set('contact.call', false);

        app()->forgetScopedInstances();
        $this->assertFalse(app(FeatureManager::class)->enabled('contact.call'));
    }

    public function test_unknown_feature_throws_in_tests_so_typos_are_caught(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->features()->enabled('does.not.exist');
    }

    public function test_cannot_set_unknown_feature(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->features()->set('does.not.exist', true);
    }

    public function test_grouped_list_for_admin_page(): void
    {
        $groups = $this->features()->grouped();

        $this->assertArrayHasKey('Sign-in', $groups);
        $this->assertArrayHasKey('Phase 2', $groups);
        $this->assertSame('login.google', $groups['Sign-in'][0]['key']);
    }

    public function test_route_returns_404_when_feature_is_off(): void
    {
        Route::middleware(['web', 'feature:chat'])->get('/_test/chat', fn () => 'chat page');

        $this->get('/_test/chat')->assertNotFound();

        $this->features()->set('chat', true);
        $this->get('/_test/chat')->assertOk()->assertSee('chat page');
    }

    public function test_blade_directive_hides_content_when_off(): void
    {
        $template = "@feature('contact.whatsapp')\nWHATSAPP\n@endfeature";

        $this->assertSame('WHATSAPP', trim(Blade::render($template)));

        $this->features()->set('contact.whatsapp', false);
        $this->assertSame('', trim(Blade::render($template)));
    }

    public function test_helper_function(): void
    {
        $this->assertTrue(feature('ads.posting'));
    }
}
