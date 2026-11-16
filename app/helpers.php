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

if (! function_exists('lroute')) {
    /** Link to a public page in the current language (adds /so/ when needed). */
    function lroute(string $name, array $parameters = [], bool $absolute = true): string
    {
        $locale = app()->getLocale();

        if ($locale === config('iibsom.default_locale')) {
            return route($name, $parameters, $absolute);
        }

        return route('loc.'.$name, ['locale' => $locale] + $parameters, $absolute);
    }
}
