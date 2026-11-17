<?php

namespace Tests\Feature;

use App\Support\Health\HealthChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    private function check(string $key): array
    {
        return collect(app(HealthChecker::class)->run())->firstWhere('key', $key);
    }

    public function test_database_and_cache_are_ok(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $this->assertSame('ok', $this->check('database')['status']);
        $this->assertContains($this->check('cache')['status'], ['ok', 'warn']);
        $this->assertSame('ok', $this->check('storage')['status']);
    }

    public function test_scheduler_fails_until_heartbeat_then_ok(): void
    {
        $this->assertSame('fail', $this->check('scheduler')['status']);
        $this->assertStringContainsString('schedule:run', $this->check('scheduler')['fix']);

        app(HealthChecker::class)->beat();
        $this->assertSame('ok', $this->check('scheduler')['status']);
    }

    public function test_old_heartbeat_means_cron_stopped(): void
    {
        Cache::forever(HealthChecker::HEARTBEAT_KEY, now()->subMinutes(10)->getTimestamp());

        $this->assertSame('fail', $this->check('scheduler')['status']);
    }

    public function test_heartbeat_is_scheduled_every_minute(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('heartbeat')->assertSuccessful();
    }

    public function test_health_command_prints_checks(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        app(HealthChecker::class)->beat();

        $this->artisan('iibsom:health')
            ->expectsOutputToContain('Database')
            ->expectsOutputToContain('Scheduler (cron)');
    }

    public function test_debug_in_production_fails(): void
    {
        config(['app.debug' => true]);
        $this->app['env'] = 'production';

        $this->assertSame('fail', $this->check('debug')['status']);
    }
}
