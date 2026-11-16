<?php

namespace App\Support\Help;

use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Help articles for customers: resources/help/{locale}/{order}-{slug}.md.
 * The first line is "# Title". Words like :max_photos are filled in from
 * Settings, so the help always matches the real limits.
 */
class HelpCentre
{
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

        $html = Str::markdown(self::fill($body), [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 10,
        ]);

        return ['title' => $title, 'html' => new HtmlString($html)];
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
        $found = [];
        foreach ([config('iibsom.default_locale'), $locale] as $lang) {
            $dir = resource_path("help/{$lang}");
            if (! File::isDirectory($dir)) {
                continue;
            }
            $files = File::files($dir);
            usort($files, fn ($a, $b) => strnatcmp($a->getFilename(), $b->getFilename()));
            foreach ($files as $file) {
                if (preg_match('/^\d+-([a-z0-9-]+)\.md$/', $file->getFilename(), $m) === 1) {
                    $found[$m[1]] = $file->getPathname();
                }
            }
        }

        return $found;
    }

    /** @return array{title: string, body: string} */
    private static function parse(string $path): array
    {
        $text = str_replace("\r\n", "\n", File::get($path));
        [$first, $body] = array_pad(explode("\n", $text, 2), 2, '');

        return ['title' => trim(ltrim($first, '# ')), 'body' => trim($body)];
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
