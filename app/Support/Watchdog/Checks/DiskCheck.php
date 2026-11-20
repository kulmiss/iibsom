<?php

namespace App\Support\Watchdog\Checks;

use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Probes\Disk;
use App\Support\Watchdog\Watchdog;

/** Disk space: warning at the limit (80% by default), urgent at 90%. */
final class DiskCheck implements Check
{
    public function __construct(private readonly Disk $disk) {}

    public function key(): string
    {
        return 'health.disk';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        $space = $this->disk->space(storage_path());
        if ($space === null) {
            return []; // this host does not tell; the uploads limit covers shared hosting
        }
        $used = (int) floor(100 * (1 - $space['free'] / $space['total']));
        $level = $used >= 90 ? 'urgent' : ($used >= $limit ? 'warning' : null);
        if ($level === null) {
            return [];
        }
        $freeGb = number_format($space['free'] / 1024 ** 3, 1);

        return [new Finding($this->key(), $level, "The disk is {$used}% full",
            "Only {$freeGb} GB is free. When it is full, photos and the database cannot be saved and the site stops working.",
            ['Delete old backups and old log files (storage/logs).', 'Ask your host for more space.'],
            ['used_percent' => $used, 'free_gb' => (float) $freeGb])];
    }
}
