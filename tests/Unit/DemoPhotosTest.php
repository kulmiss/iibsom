<?php

namespace Tests\Unit;

use Database\Seeders\DemoSeeder;
use PHPUnit\Framework\TestCase;

/** The committed demo photos match the demo titles (database/demo). */
class DemoPhotosTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2).'/database/demo';
    }

    /** @return list<array<string, mixed>> */
    private function photos(): array
    {
        $list = json_decode((string) file_get_contents($this->root().'/photos.json'), true);

        return is_array($list) ? array_values($list) : [];
    }

    public function test_every_photo_word_has_a_photo(): void
    {
        $kinds = array_column($this->photos(), 'kind');
        $missing = [];
        foreach (require $this->root().'/content.php' as $items) {
            foreach ($items as $item) {
                if (isset($item[3]) && ! in_array(DemoSeeder::kind($item[3]), $kinds, true)) {
                    $missing[] = $item[3];
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), 'Run: php tools/download-demo-photos');
    }

    public function test_every_listed_photo_exists_with_a_free_licence_and_credit(): void
    {
        foreach ($this->photos() as $p) {
            $this->assertFileExists($this->root().'/photos/'.$p['file']);
            $this->assertMatchesRegularExpression('/^(CC0 1\.0|Public Domain Mark|CC BY [\d.]+|Pexels License|Unsplash License)$/', (string) $p['licence'], $p['file']);
            $this->assertNotEmpty($p['source_url'], $p['file']);
        }
        // No photo file without an entry (it would have no credit).
        $listed = array_column($this->photos(), 'file');
        foreach (glob($this->root().'/photos/*.jpg') ?: [] as $file) {
            $this->assertContains(basename($file), $listed);
        }
    }

    public function test_kind_slug(): void
    {
        $this->assertSame('toyota-land-cruiser', DemoSeeder::kind('Toyota Land Cruiser'));
        $this->assertSame('nissan-x-trail', DemoSeeder::kind('nissan x-trail'));
    }
}
