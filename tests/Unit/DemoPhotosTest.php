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
            $this->assertMatchesRegularExpression('/^(CC0( 1\.0)?|Public Domain( Mark)?|PD[- ].*|CC BY [\d.]+|Pexels License|Unsplash License)$/i', (string) $p['licence'], $p['file']);
            $this->assertNotEmpty($p['source_url'], $p['file']);
        }
        // No photo file without an entry (it would have no credit).
        $listed = array_column($this->photos(), 'file');
        foreach (glob($this->root().'/photos/*.jpg') ?: [] as $file) {
            $this->assertContains(basename($file), $listed);
        }
    }

    /** Every demo ad has a photo (owner, 2026-11-21): every title has photo words, and an ad always gets 1–3. */
    public function test_every_title_has_photo_words_and_every_ad_gets_a_photo(): void
    {
        $without = [];
        foreach (require $this->root().'/content.php' as $category => $items) {
            foreach ($items as $item) {
                if (! isset($item[3]) || trim((string) $item[3]) === '') {
                    $without[] = "{$category}: {$item[0]}";
                }
            }
        }
        $this->assertSame([], $without);

        mt_srand(1);
        for ($i = 0; $i < 200; $i++) {
            $picked = DemoSeeder::pickPhotos(['a.jpg', 'b.jpg', 'c.jpg', 'd.jpg']);
            $this->assertGreaterThanOrEqual(1, count($picked));
            $this->assertLessThanOrEqual(3, count($picked));
        }
        $this->assertSame(['a.jpg'], DemoSeeder::pickPhotos(['a.jpg']));
        $this->assertSame([], DemoSeeder::pickPhotos([]));
    }

    public function test_short_titles_get_a_few_more_words(): void
    {
        $this->assertStringStartsWith('Plumber – ', DemoSeeder::mediumTitle('Plumber', 'services'));
        $this->assertGreaterThanOrEqual(DemoSeeder::MEDIUM_TITLE, mb_strlen(DemoSeeder::mediumTitle('Plumber', 'services')));
        $long = 'Toyota Land Cruiser 2015, V8, full option';
        $this->assertSame($long, DemoSeeder::mediumTitle($long, 'vehicles'));
        foreach (require $this->root().'/content.php' as $items) {
            foreach ($items as $item) {
                $this->assertLessThanOrEqual(80, mb_strlen(DemoSeeder::mediumTitle($item[0], 'services')), $item[0]);
            }
        }
    }

    public function test_kind_slug(): void
    {
        $this->assertSame('toyota-land-cruiser', DemoSeeder::kind('Toyota Land Cruiser'));
        $this->assertSame('nissan-x-trail', DemoSeeder::kind('nissan x-trail'));
    }
}
