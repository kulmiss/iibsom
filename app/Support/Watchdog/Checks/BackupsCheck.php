<?php

namespace App\Support\Watchdog\Checks;

use App\Support\Watchdog\Finding;
use App\Support\Watchdog\State;
use App\Support\Watchdog\Watchdog;
use Illuminate\Support\Carbon;

/**
 * Backups are made outside the app (a nightly mysqldump, docs/deploy-vps.md). This checks the folder:
 * the newest file is fresh (limit in hours), its size is normal (at least half of the usual), and once a week
 * that the file opens and looks like a database dump. Never a restore on the live site.
 */
final class BackupsCheck implements Check
{
    public function key(): string
    {
        return 'health.backups';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        $folder = (string) config('watchdog.backup_path');
        if ($folder === '') {
            return [new Finding($this->key().':not_set', 'info', 'Backups are not watched yet',
                'Set WATCHDOG_BACKUP_PATH in .env to the folder of your nightly backups.',
                ['Make a nightly backup (docs/deploy-vps.md → Backups).', 'Put its folder in WATCHDOG_BACKUP_PATH.'])];
        }

        $files = glob(rtrim($folder, '/').'/'.config('watchdog.backup_pattern')) ?: [];
        $files = array_values(array_filter($files, 'is_file'));
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        if ($files === []) {
            return [new Finding($this->key().':missing', 'urgent', 'No backup found',
                'There is no backup file in the backup folder. If the server breaks, the ads and accounts are lost.',
                ['Check that the nightly backup cron exists (docs/deploy-vps.md → Backups).', 'Check WATCHDOG_BACKUP_PATH and WATCHDOG_BACKUP_PATTERN in .env.'])];
        }

        $found = [];
        $newest = $files[0];
        $at = Carbon::createFromTimestamp((int) filemtime($newest));
        $hours = (int) floor($at->diffInHours(now()));
        State::put('backup', ['at' => $at->getTimestamp(), 'bytes' => (int) filesize($newest)]);

        if ($hours >= $limit) {
            $found[] = new Finding($this->key().':old', 'urgent', 'Last night\'s backup did not run',
                "The newest backup is {$hours} hours old. Normally there is a new one every night.",
                ['Check the nightly backup cron on the server.', 'Make one now: see docs/deploy-vps.md → Backups.'], ['hours' => $hours]);
        }

        $older = array_map('filesize', array_slice($files, 1, 7));
        if (count($older) >= 3) {
            sort($older);
            $usual = $older[intdiv(count($older), 2)];
            $size = (int) filesize($newest);
            if ($usual > 0 && $size < $usual / 2) {
                $found[] = new Finding($this->key().':small', 'warning', 'The newest backup is much smaller than normal',
                    'It is '.self::mb($size).', the usual size is '.self::mb((int) $usual).'. Part of the data may be missing.',
                    ['Open the backup log or run the backup by hand and look for errors.'], ['bytes' => $size, 'usual_bytes' => (int) $usual]);
            }
        }

        $lastTest = (int) State::get('backup_tested_at', 0);
        if ($lastTest <= now()->subDays(7)->getTimestamp() || State::get('backup_test_failed') !== null) { // a failed test is tried again every day
            $ok = self::opens($newest);
            State::put('backup_tested_at', now()->getTimestamp());
            State::put('backup_test_failed', $ok ? null : $newest);
            if (! $ok) {
                $found[] = new Finding($this->key().':broken', 'urgent', 'The backup file does not open',
                    'The weekly test could not read the newest backup as a database dump. A broken backup cannot save you.',
                    ['Make a new backup by hand and check that it finishes.', 'Check the free disk space during the backup.'], []);
            }
        }

        return $found;
    }

    /** Reads the start of the file (gzip or plain) and looks for a MySQL / MariaDB dump header. */
    public static function opens(string $file): bool
    {
        $gz = str_ends_with($file, '.gz');
        $handle = $gz ? @gzopen($file, 'rb') : @fopen($file, 'rb');
        if ($handle === false) {
            return false;
        }
        $start = $gz ? @gzread($handle, 65536) : @fread($handle, 65536);
        $gz ? gzclose($handle) : fclose($handle);

        return is_string($start) && preg_match('/^-- (MySQL|MariaDB) dump|CREATE TABLE/m', $start) === 1;
    }

    private static function mb(int $bytes): string
    {
        return number_format($bytes / 1024 ** 2, 1).' MB';
    }
}
