<?php

namespace App\Support\Features;

use App\Support\Cache\PageCache;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * One switch for every feature.
 *
 * Default value comes from config/features.php; a Super Admin change is
 * stored in the `features` table and wins. Results are cached, and the
 * cache is cleared whenever a switch changes.
 */
class FeatureManager
{
    public const CACHE_KEY = 'iibsom.features.v1';

    /** @var array<string, bool>|null */
    private ?array $resolved = null;

    public function __construct(private readonly Cache $cache) {}

    /** Is this feature ON? Unknown keys are OFF (and throw in tests). */
    public function enabled(string $key): bool
    {
        if (! $this->exists($key)) {
            if (app()->runningUnitTests()) {
                throw new InvalidArgumentException("Unknown feature switch [{$key}]. Add it to config/features.php.");
            }

            return false;
        }

        return $this->all()[$key];
    }

    public function disabled(string $key): bool
    {
        return ! $this->enabled($key);
    }

    public function exists(string $key): bool
    {
        return array_key_exists($key, $this->definitions());
    }

    /** Turn a feature ON or OFF (saved in the database). */
    public function set(string $key, bool $enabled, ?int $userId = null): void
    {
        if (! $this->exists($key)) {
            throw new InvalidArgumentException("Unknown feature switch [{$key}].");
        }

        DB::table('features')->upsert(
            [['key' => $key, 'enabled' => $enabled, 'updated_by' => $userId, 'created_at' => now(), 'updated_at' => now()]],
            ['key'],
            ['enabled', 'updated_by', 'updated_at'],
        );

        $this->flush();
    }

    /** Go back to the default value from config. */
    public function reset(string $key): void
    {
        DB::table('features')->where('key', $key)->delete();
        $this->flush();
    }

    /** @return array<string, bool> key => ON/OFF for every feature */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $overrides = $this->cache->remember(
            self::CACHE_KEY,
            config('iibsom.cache_ttl'),
            fn () => DB::table('features')->pluck('enabled', 'key')->map(fn ($v) => (bool) $v)->all(),
        );

        $values = [];
        foreach ($this->definitions() as $key => $definition) {
            $values[$key] = $overrides[$key] ?? (bool) $definition['default'];
        }

        return $this->resolved = $values;
    }

    /**
     * Grouped list for the admin "Feature switches" page.
     *
     * @return array<string, list<array{key: string, label: string, description: string, enabled: bool, default: bool}>>
     */
    public function grouped(): array
    {
        $all = $this->all();
        $groups = [];

        foreach ($this->definitions() as $key => $definition) {
            $groups[$definition['group']][] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'enabled' => $all[$key],
                'default' => (bool) $definition['default'],
            ];
        }

        return $groups;
    }

    public function flush(): void
    {
        PageCache::bump();
        $this->cache->forget(self::CACHE_KEY);
        $this->resolved = null;
    }

    /** @return array<string, array{group: string, default: bool, label: string, description: string}> */
    private function definitions(): array
    {
        return config('features', []);
    }
}
