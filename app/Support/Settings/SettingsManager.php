<?php

namespace App\Support\Settings;

use App\Support\Cache\PageCache;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Settings the Super Admin can change without code (ad expiry days, limits, ...).
 * Defaults live in config/iibsom.php → settings; saved values win.
 */
class SettingsManager
{
    public const CACHE_KEY = 'iibsom.settings.v1';

    /** @var array<string, mixed>|null */
    private ?array $resolved = null;

    public function __construct(private readonly Cache $cache) {}

    public function get(string $key, mixed $fallback = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : $fallback;
    }

    public function set(string $key, mixed $value, ?int $userId = null): void
    {
        if (! array_key_exists($key, $this->defaults())) {
            throw new InvalidArgumentException("Unknown setting [{$key}]. Add a default to config/iibsom.php.");
        }

        $default = $this->defaults()[$key];
        if (gettype($default) !== gettype($value)) {
            throw new InvalidArgumentException("Setting [{$key}] must be of type ".gettype($default).'.');
        }

        DB::table('settings')->upsert(
            [['key' => $key, 'value' => json_encode($value), 'updated_by' => $userId, 'created_at' => now(), 'updated_at' => now()]],
            ['key'],
            ['value', 'updated_by', 'updated_at'],
        );

        $this->flush();
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $saved = $this->cache->remember(
            self::CACHE_KEY,
            config('iibsom.cache_ttl'),
            fn () => DB::table('settings')->pluck('value', 'key')->map(fn ($v) => json_decode($v, true))->all(),
        );

        return $this->resolved = array_merge($this->defaults(), array_intersect_key($saved, $this->defaults()));
    }

    public function flush(): void
    {
        PageCache::bump();
        $this->cache->forget(self::CACHE_KEY);
        $this->resolved = null;
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return config('iibsom.settings', []);
    }
}
