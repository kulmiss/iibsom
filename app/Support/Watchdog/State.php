<?php

namespace App\Support\Watchdog;

use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Small Watchdog memory kept in a file (storage/app/watchdog/state.json), not in the database or cache:
 * it must still work when the database (or a database cache) is down, and it must not empty the page cache.
 * Holds times like "last run" and "digest sent on", never personal data.
 */
final class State
{
    public static function path(): string
    {
        return (string) (config('watchdog.state_path') ?: storage_path('app/watchdog/state.json'));
    }

    public static function get(string $key, mixed $fallback = null): mixed
    {
        return self::read()[$key] ?? $fallback;
    }

    public static function put(string $key, mixed $value): void
    {
        self::change(function (array $state) use ($key, $value) {
            $state[$key] = $value;

            return $state;
        });
    }

    public static function forget(string $key): void
    {
        self::change(function (array $state) use ($key) {
            unset($state[$key]);

            return $state;
        });
    }

    /** @return array<string, mixed> */
    public static function read(): array
    {
        try {
            if (! is_file(self::path())) {
                return [];
            }
            $data = json_decode((string) file_get_contents(self::path()), true);

            return is_array($data) ? $data : [];
        } catch (Throwable) {
            return [];
        }
    }

    /** @param  callable(array<string, mixed>): array<string, mixed>  $change */
    private static function change(callable $change): void
    {
        File::ensureDirectoryExists(dirname(self::path()));
        $handle = fopen(self::path(), 'c+');
        if ($handle === false) {
            return;
        }
        try {
            flock($handle, LOCK_EX);
            $data = json_decode((string) stream_get_contents($handle), true);
            $state = $change(is_array($data) ? $data : []);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($state, JSON_PRETTY_PRINT));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
