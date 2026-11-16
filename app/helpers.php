<?php

use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;

if (! function_exists('feature')) {
    /** Is this feature switch ON? */
    function feature(string $key): bool
    {
        return app(FeatureManager::class)->enabled($key);
    }
}

if (! function_exists('setting')) {
    /** Read a Super Admin setting (falls back to the default in config/iibsom.php). */
    function setting(string $key, mixed $fallback = null): mixed
    {
        return app(SettingsManager::class)->get($key, $fallback);
    }
}
