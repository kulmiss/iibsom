<?php

namespace Tests\Feature\Support;

use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SettingsManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_match_owner_decisions(): void
    {
        $this->assertSame(60, setting('ads.expiry_days'));
        $this->assertSame(5, setting('ads.max_per_day'));
        $this->assertSame(8, setting('ads.max_photos'));
        $this->assertSame('USD', setting('currency'));
        $this->assertSame(['+252'], setting('phone.country_codes'));
    }

    public function test_saved_value_wins_and_survives_new_request(): void
    {
        app(SettingsManager::class)->set('ads.expiry_days', 30);

        app()->forgetScopedInstances();
        $this->assertSame(30, setting('ads.expiry_days'));
    }

    public function test_wrong_type_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(SettingsManager::class)->set('ads.expiry_days', 'thirty');
    }

    public function test_unknown_setting_is_refused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        app(SettingsManager::class)->set('nope', 1);
    }

    public function test_fallback_for_missing_key(): void
    {
        $this->assertSame('x', setting('missing.key', 'x'));
    }
}
