<?php

namespace App\Support\Routing;

use App\Models\Page;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/**
 * Web addresses the site already uses as the first part of a URL
 * (/search, /login, /so, /safety, /build ...). A category with one of
 * these addresses could never be opened, so the admin refuses them.
 */
class ReservedSlugs
{
    /** @return list<string> */
    public static function all(): array
    {
        $words = array_merge(
            array_keys(config()->array('iibsom.locales')),
            Page::CORE,
            ['help'], // help centre (/help) — kept even while it is switched OFF
        );

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $first = explode('/', trim($route->uri(), '/'))[0];
            if ($first !== '' && ! str_contains($first, '{')) {
                $words[] = $first;
            }
        }

        // Folders and files in public/ (build, storage, icons, sw.js ...).
        foreach (array_merge(File::directories(public_path()), File::files(public_path())) as $item) {
            $words[] = basename((string) $item);
        }

        $words = array_values(array_unique(array_map('strtolower', $words)));
        sort($words);

        return $words;
    }

    public static function has(string $slug): bool
    {
        return in_array(strtolower($slug), self::all(), true);
    }
}
