<?php

use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Support\HtmlString;

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

if (! function_exists('translated')) {
    /** Pick the current language from {"en": "...", "so": "..."}; fall back to English. */
    function translated(array|string|null $value): string
    {
        if (! is_array($value)) {
            return (string) $value;
        }

        return (string) ($value[app()->getLocale()] ?? $value[config('iibsom.default_locale')] ?? reset($value) ?: '');
    }
}

if (! function_exists('icon')) {
    /** Inline SVG icon from resources/svg/icons (Lucide). Decorative: aria-hidden. */
    function icon(string $name, string $class = 'size-5'): HtmlString
    {
        static $cache = [];

        $svg = $cache[$name] ??= (function () use ($name) {
            $path = resource_path("svg/icons/{$name}.svg");
            $raw = is_file($path) ? file_get_contents($path) : file_get_contents(resource_path('svg/icons/tag.svg'));

            // Drop the licence comment and the size/class attributes; we set our own.
            $raw = preg_replace('/<!--.*?-->\s*/s', '', $raw);
            // Only on the <svg> tag itself: inner shapes (<rect width=...>) must keep theirs.
            $raw = preg_replace_callback('/<svg\b[^>]*>/', fn ($m) => preg_replace('/\s(class|width|height)="[^"]*"/', '', $m[0]), $raw, 1);

            return preg_replace('/\s+/', ' ', trim($raw));
        })();

        return new HtmlString(
            str_replace('<svg ', '<svg class="'.e($class).'" aria-hidden="true" focusable="false" ', $svg)
        );
    }
}
