<?php

namespace App\Support\Localization;

use App\Support\Settings\SettingsManager;
use Illuminate\Http\Request;

/**
 * Languages: which exist, which are ON, and the URL for a page in each.
 * Default language (English) has no prefix: /cars ; others: /so/cars
 */
class Locales
{
    public function __construct(private readonly SettingsManager $settings) {}

    public function default(): string
    {
        return config('iibsom.default_locale');
    }

    /** @return array<string, array{name: string, native: string, dir: string}> */
    public function supported(): array
    {
        return config('iibsom.locales');
    }

    /**
     * Codes that are ON (the default language is always ON).
     *
     * @return list<string>
     */
    public function enabled(): array
    {
        $on = (array) $this->settings->get('languages.enabled', []);
        $codes = array_values(array_filter(
            array_keys($this->supported()),
            fn (string $code) => $code === $this->default() || in_array($code, $on, true),
        ));

        return $codes;
    }

    public function isEnabled(string $code): bool
    {
        return in_array($code, $this->enabled(), true);
    }

    /** Non-default codes, used as the URL prefix pattern. */
    public function prefixPattern(): string
    {
        $codes = array_diff(array_keys($this->supported()), [$this->default()]);

        return $codes ? implode('|', array_map('preg_quote', $codes)) : '(?!)';
    }

    /** Path without the language prefix: "/so/cars" → "/cars". */
    public function stripPrefix(string $path): string
    {
        $path = '/'.ltrim($path, '/');

        if (preg_match('#^/('.$this->prefixPattern().')(/.*)?$#', $path, $m)) {
            return $m[2] ?? '/';
        }

        return $path;
    }

    /** URL of the same page in another language. */
    public function urlFor(string $code, ?Request $request = null): string
    {
        $request ??= request();
        $path = $this->stripPrefix($request->getPathInfo());
        $prefix = $code === $this->default() ? '' : '/'.$code;
        $query = $request->getQueryString();

        return url(rtrim($prefix.$path, '/') ?: '/').($query ? '?'.$query : '');
    }

    /** Should the language switcher show? Only when 2+ languages are ON. */
    public function showSwitcher(): bool
    {
        return count($this->enabled()) > 1 && feature('site.language_switch');
    }
}
