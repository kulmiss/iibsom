<?php

namespace App\Support\Help;

/**
 * The Super Admin guide: resources/help/admin/{order}-{slug}.md (English).
 * Shown in the admin ("?" next to each page title, and the Guide page),
 * printable, and as a PDF made by tools/make-guide-pdf.
 */
class AdminGuide
{
    use ReadsMarkdownArticles;

    public const PDF = 'help/admin-guide.pdf';

    /** @return list<array{slug: string, title: string, html: string}> */
    public static function sections(): array
    {
        $list = [];
        foreach (self::filesIn(self::dir()) as $slug => $path) {
            ['title' => $title, 'body' => $body] = self::parse($path);
            $list[] = ['slug' => $slug, 'title' => $title, 'html' => (string) self::toHtml($body)];
        }

        return $list;
    }

    /** Changes whenever any section changes: the PDF must be made again then. */
    public static function version(): string
    {
        $text = '';
        foreach (self::filesIn(self::dir()) as $slug => $path) {
            $text .= $slug."\n".str_replace("\r\n", "\n", (string) file_get_contents($path))."\n";
        }

        return substr(hash('sha256', $text), 0, 16);
    }

    private static function dir(): string
    {
        return resource_path('help/admin');
    }
}
