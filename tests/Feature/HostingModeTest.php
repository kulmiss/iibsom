<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CI runs the whole test suite twice: IIBSOM_HOSTING=vps and =shared.
 */
class HostingModeTest extends TestCase
{
    use RefreshDatabase;

    private function scheduledNames(): array
    {
        return collect(app(Schedule::class)->events())->map(fn ($e) => $e->description)->all();
    }

    public function test_hosting_mode_is_vps_or_shared(): void
    {
        $this->assertContains(config('iibsom.hosting'), ['vps', 'shared']);
    }

    public function test_unknown_mode_falls_back_to_vps(): void
    {
        $old = [$_SERVER['IIBSOM_HOSTING'] ?? null, $_ENV['IIBSOM_HOSTING'] ?? null, getenv('IIBSOM_HOSTING')];
        $_SERVER['IIBSOM_HOSTING'] = $_ENV['IIBSOM_HOSTING'] = 'banana';
        putenv('IIBSOM_HOSTING=banana');

        try {
            $config = require base_path('config/iibsom.php');
        } finally {
            [$server, $env, $put] = $old;
            if ($server === null) {
                unset($_SERVER['IIBSOM_HOSTING']);
            } else {
                $_SERVER['IIBSOM_HOSTING'] = $server;
            }
            if ($env === null) {
                unset($_ENV['IIBSOM_HOSTING']);
            } else {
                $_ENV['IIBSOM_HOSTING'] = $env;
            }
            putenv($put === false ? 'IIBSOM_HOSTING' : "IIBSOM_HOSTING={$put}");
        }

        $this->assertSame('vps', $config['hosting']);
    }

    public function test_cron_works_the_queue_only_in_shared_mode(): void
    {
        $names = $this->scheduledNames();

        if (config('iibsom.hosting') === 'shared') {
            $this->assertContains('shared-queue-worker', $names);
        } else {
            $this->assertNotContains('shared-queue-worker', $names);
        }
    }
}
