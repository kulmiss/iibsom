<?php

namespace App\Support\Watchdog\Probes;

/** Disk numbers (a class of its own so tests can say "the disk is 95% full"). */
class Disk
{
    /** @return array{free: float, total: float}|null  null when the host does not tell (some shared hosts). */
    public function space(string $path): ?array
    {
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        return ($free === false || $total === false || $total <= 0) ? null : ['free' => (float) $free, 'total' => (float) $total];
    }

    /** Size of a folder in bytes (files only). */
    public function folderSize(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }
        $size = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }
}
