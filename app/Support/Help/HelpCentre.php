<?php

namespace App\Support\Help;

use Illuminate\Support\HtmlString;

/**
 * Help articles for customers: resources/help/{locale}/{order}-{slug}.md.
 * The first line is "# Title". Words like :max_photos are filled in from
 * Settings, so the help always matches the real limits.
 */
class HelpCentre
{
    use ReadsMarkdownArticles;

    /** @return list<array{slug: string, title: string}> */
    public static function articles(string $locale): array
    {
        $list = [];
        foreach (self::files($locale) as $slug => $path) {
            $list[] = ['slug' => $slug, 'title' => self::parse($path)['title']];
        }

        return $list;
    }

    /** @return array{title: string, html: HtmlString}|null */
    public static function article(string $locale, string $slug): ?array
    {
        $path = self::files($locale)[$slug] ?? null;
        if ($path === null) {
            return null;
        }
        ['title' => $title, 'body' => $body] = self::parse($path);

        return ['title' => $title, 'html' => self::toHtml(self::fill($body))];
    }

    /**
     * Raw text of every article (for the Somali review sheet).
     *
     * @return array<string, array{title: string, body: string}>
     */
    public static function raw(string $locale): array
    {
        return array_map(fn (string $path) => self::parse($path), self::files($locale));
    }

    /**
     * Articles in their file order. Somali falls back to English for an
     * article that has no Somali file yet (a test keeps both complete).
     *
     * @return array<string, string> slug => path
     */
    private static function files(string $locale): array
    {
        $files = array_merge(
            self::filesIn(resource_path('help/'.config('iibsom.default_locale'))),
            self::filesIn(resource_path("help/{$locale}")),
        );
        if (! feature('help.how_to_sell')) {
            unset($files['how-to-sell']); // switch "help.how_to_sell"
        }

        return $files;
    }

    private static function fill(string $body): string
    {
        return strtr($body, [
            ':max_photos' => (string) setting('ads.max_photos'),
            ':max_per_day' => (string) setting('ads.max_per_day'),
            ':expiry_days' => (string) setting('ads.expiry_days'),
            ':renew_days' => (string) setting('ads.renew_days_before'),
            ':safety_url' => lroute('page', ['slug' => 'safety']),
            ':help_url' => lroute('help'),
        ]);
    }
}
