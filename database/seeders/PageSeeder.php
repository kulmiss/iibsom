<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Starter text for the 4 core pages (database/seeders/data/pages.php).
 * Only adds missing text: text edited in the admin is never overwritten.
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        ['en' => $pages, 'so' => $somali] = require database_path('seeders/data/pages.php');

        foreach ($pages as $slug => [$title, $body]) {
            $page = Page::firstOrNew(['slug' => $slug]);
            if (! $page->exists) {
                $page->enabled = true;
            }
            // Only add what is missing: text edited in the admin stays as it is.
            $page->title = ($page->title ?? []) + ['en' => $title, 'so' => $somali[$slug][0]];
            $page->body = ($page->body ?? []) + ['en' => $body, 'so' => $somali[$slug][1]];
            $page->save();
        }
    }
}
