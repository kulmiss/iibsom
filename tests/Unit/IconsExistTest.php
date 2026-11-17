<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class IconsExistTest extends TestCase
{
    /** icon() falls back to a tag icon for missing files, so a missing icon never shows as an error: test it here. */
    public function test_every_icon_used_in_a_view_exists(): void
    {
        $missing = [];
        foreach (File::allFiles(resource_path('views')) as $file) {
            preg_match_all("/icon\\('([a-z0-9-]+)'/", $file->getContents(), $m);
            foreach (array_unique($m[1]) as $name) {
                if (! is_file(resource_path("svg/icons/{$name}.svg"))) {
                    $missing[] = "{$name} (in {$file->getRelativePathname()})";
                }
            }
        }

        $this->assertSame([], $missing);
    }
}
