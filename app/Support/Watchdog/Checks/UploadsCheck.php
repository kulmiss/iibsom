<?php

namespace App\Support\Watchdog\Checks;

use App\Support\Watchdog\Finding;
use App\Support\Watchdog\Probes\Disk;
use App\Support\Watchdog\State;
use App\Support\Watchdog\Watchdog;

/** Size of the uploads folder; with a limit (shared hosting plans): warning at 80%, urgent at 90%. */
final class UploadsCheck implements Check
{
    public function __construct(private readonly Disk $disk) {}

    public function key(): string
    {
        return 'health.uploads';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        $mb = (int) round($this->disk->folderSize(storage_path('app/public')) / 1024 ** 2);
        State::put('uploads_mb', $mb);
        if ($limit <= 0) {
            return [];
        }
        $percent = (int) floor(100 * $mb / $limit);
        $level = $percent >= 90 ? 'urgent' : ($percent >= 80 ? 'warning' : null);

        return $level === null ? [] : [new Finding($this->key(), $level, "The uploads folder is {$percent}% of your limit",
            "Uploads use {$mb} MB of the {$limit} MB you set. When it is full, new photos cannot be saved.",
            ['Move photos to Cloudflare R2 (docs/cloudflare-r2.md).', 'Or ask your host for a bigger plan and change the limit here.'],
            ['mb' => $mb, 'percent' => $percent])];
    }
}
