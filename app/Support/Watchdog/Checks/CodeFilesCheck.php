<?php

namespace App\Support\Watchdog\Checks;

use App\Support\Watchdog\Finding;
use App\Support\Watchdog\State;
use App\Support\Watchdog\Watchdog;
use Illuminate\Support\Facades\File;

/**
 * A fingerprint (sha256) of every file in the code folders, compared with the night before.
 * Changes are told once and become the new normal. After an update, run `php artisan iibsom:watchdog:baseline`
 * so your own update is not reported.
 */
final class CodeFilesCheck implements Check
{
    public function key(): string
    {
        return 'security.code_files';
    }

    public function run(Watchdog $watchdog, int $limit): array
    {
        $now = self::fingerprints();
        $before = self::saved();
        self::save($now);
        if ($before === null) {
            return []; // first night: this is the starting point
        }

        $changed = array_keys(array_diff_assoc($now, $before));
        $removed = array_keys(array_diff_key($before, $now));
        $all = array_merge($changed, $removed);
        if ($all === []) {
            return [];
        }
        sort($all);
        $count = count($all);
        $list = implode(', ', array_slice($all, 0, 5)).($count > 5 ? ' and '.($count - 5).' more' : '');

        $watchdog->event(new Finding($this->key(), 'warning', "{$count} code file(s) changed",
            "Since the last check these files were added, changed or removed: {$list}.",
            ['If you or your developer updated the site, all is fine (next time run: php artisan iibsom:watchdog:baseline).', 'If nobody updated the site, someone may have broken in: tell your developer and your host now.'],
            ['files' => $count]));

        return [];
    }

    /** @return array<string, string>  path → sha256 */
    public static function fingerprints(): array
    {
        $hashes = [];
        foreach (config('watchdog.code_folders') as $relative) {
            $path = base_path($relative);
            $files = is_dir($path) ? File::allFiles($path, true) : (is_file($path) ? [new \SplFileInfo($path)] : []);
            foreach ($files as $file) {
                $real = $file->getPathname();
                $hashes[str_replace('\\', '/', substr($real, strlen(base_path()) + 1))] = (string) hash_file('sha256', $real);
            }
        }
        ksort($hashes);

        return $hashes;
    }

    public static function baseline(): int
    {
        $now = self::fingerprints();
        self::save($now);

        return count($now);
    }

    /** @return array<string, string>|null */
    private static function saved(): ?array
    {
        $file = self::path();
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($data) ? $data : null;
    }

    /** @param  array<string, string>  $hashes */
    private static function save(array $hashes): void
    {
        File::ensureDirectoryExists(dirname(self::path()));
        File::put(self::path(), (string) json_encode($hashes));
    }

    private static function path(): string
    {
        return preg_replace('/\.json$/', '', State::path()).'.code-fingerprints.json';
    }
}
