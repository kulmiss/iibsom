<?php

namespace Tests\Feature;

use Tests\TestCase;

class TranslationExportTest extends TestCase
{
    public function test_exports_review_csv_for_somali(): void
    {
        $path = sys_get_temp_dir().'/iibsom-so-test.csv';
        @unlink($path);

        $this->artisan('iibsom:translations:export', ['locale' => 'so', '--path' => $path])
            ->expectsOutputToContain('missing')
            ->assertSuccessful();

        $lines = array_map(fn ($l) => str_getcsv($l, escape: ''), file($path, FILE_IGNORE_NEW_LINES));
        $header = $lines[0];
        $header[0] = ltrim($header[0], "\xEF\xBB\xBF");

        $this->assertSame(['key', 'english', 'translation', 'status'], $header);
        $this->assertContains(['site.tagline', 'Buy and sell anything in Somalia', '', 'missing'], $lines);

        @unlink($path);
    }

    public function test_unknown_language_fails(): void
    {
        $this->artisan('iibsom:translations:export', ['locale' => 'xx'])->assertFailed();
    }
}
