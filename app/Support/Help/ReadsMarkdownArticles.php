<?php

namespace App\Support\Help;

use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Articles kept as Markdown files named {order}-{slug}.md, first line "# Title".
 */
trait ReadsMarkdownArticles
{
    /** @return array<string, string> slug => path, in file order */
    private static function filesIn(string $dir): array
    {
        if (! File::isDirectory($dir)) {
            return [];
        }
        $files = File::files($dir);
        usort($files, fn ($a, $b) => strnatcmp($a->getFilename(), $b->getFilename()));

        $found = [];
        foreach ($files as $file) {
            if (preg_match('/^\d+-([a-z0-9-]+)\.md$/', $file->getFilename(), $m) === 1) {
                $found[$m[1]] = $file->getPathname();
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

    private static function toHtml(string $markdown): HtmlString
    {
        return new HtmlString(Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 10,
        ]));
    }
}
