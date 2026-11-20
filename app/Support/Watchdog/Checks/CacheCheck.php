<?php

namespace App\Support\Watchdog\Checks;

use App\Support\Health\HealthChecker;
use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Watchdog;

/** The cache works (the same test as the Health page). */
final class CacheCheck implements Check
{
    public function __construct(private readonly HealthChecker $health) {}

    public function key(): string
    {
        return 'health.cache';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        $result = $this->health->only('cache');
        if ($result['status'] !== 'fail') {
            return [];
        }

        return [new Finding($this->key(), 'urgent', 'The cache does not work',
            'The site cannot save to its cache ('.config('cache.default').'). Pages are slow or show errors.',
            ['Check that Redis is running (VPS), or use CACHE_STORE=database (shared hosting).', 'Open Admin → Health for details.'])];
    }
}
