<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TranslationExportTest extends TestCase
{
    private string $csv;

    private string $screens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->csv = sys_get_temp_dir().'/iibsom-so-test-'.getmypid().'.csv';
        $this->screens = sys_get_temp_dir().'/iibsom-so-screens-'.getmypid();
        File::ensureDirectoryExists($this->screens);
        File::put($this->screens.'/home.png', 'x');
        File::put($this->screens.'/ads-show.png', 'x');
        File::put($this->screens.'/browse-index.png', 'x');
        File::put($this->screens.'/pages-safety.png', 'x');
    }

    protected function tearDown(): void
    {
        File::delete($this->csv);
        File::deleteDirectory($this->screens);
        parent::tearDown();
    }

    /** @return array<string, array<int, string>> rows keyed by text key */
    private function export(bool $withScreens = true): array
    {
        $options = ['locale' => 'so', '--path' => $this->csv];
        if ($withScreens) {
            $options['--screens'] = $this->screens;
        }
        $this->artisan('iibsom:translations:export', $options)
            ->expectsOutputToContain('needs native review')
            ->assertSuccessful();

        // fgetcsv, not file(): page texts have line breaks inside one cell.
        $lines = [];
        $handle = fopen($this->csv, 'r');
        $this->assertNotFalse($handle);
        while (($line = fgetcsv($handle, escape: '')) !== false) {
            $lines[] = $line;
        }
        fclose($handle);
        $lines[0][0] = ltrim((string) $lines[0][0], "\xEF\xBB\xBF");
        $this->assertSame(['key', 'english', 'translation', 'status', 'where', 'screenshot'], $lines[0]);

        $rows = [];
        foreach (array_slice($lines, 1) as $line) {
            $rows[(string) $line[0]] = array_map('strval', $line);
        }

        return $rows;
    }

    public function test_csv_has_text_where_it_is_used_and_its_picture(): void
    {
        $rows = $this->export();

        $this->assertSame(
            ['site.home.heading', 'What are you looking for?', 'Maxaad raadinaysaa?', 'needs native review', 'resources/views/home.blade.php', 'home.png'],
            $rows['site.home.heading'] ?? null,
        );
        // The ad page has its picture.
        $this->assertSame('ads-show.png', $rows['ads.page.details'][5]);
        $this->assertStringContainsString('resources/views/ads/show.blade.php', $rows['ads.page.details'][4]);
        // Header texts show on every page, so they point to the home picture.
        $this->assertStringContainsString('resources/views/partials/header.blade.php', $rows['site.nav.sign_in'][4]);
        $this->assertSame('home.png', $rows['site.nav.sign_in'][5]);
        // The ad card sits inside the list pages.
        $this->assertContains('browse-index.png', explode(' | ', $rows['ads.page.negotiable'][5]));
    }

    public function test_no_picture_column_without_screens_folder(): void
    {
        $rows = $this->export(withScreens: false);

        $this->assertSame([''], array_values(array_unique(array_column($rows, 5))));
    }

    public function test_keys_built_from_parts_are_found(): void
    {
        $rows = $this->export();

        // Used as __('reports.reason.'.$reason) in the code.
        $this->assertNotSame('', $rows['reports.reason.scam'][4]);
        $this->assertStringNotContainsString('not found', $rows['reports.reason.scam'][4]);
    }

    public function test_laravel_messages_list_only_what_we_translated(): void
    {
        $rows = $this->export();

        $this->assertSame('Error messages under form fields', $rows['validation.required'][4]);
        $this->assertNotSame('', $rows['validation.required'][1]);
        // Rules we do not use (and did not translate) stay out of the sheet.
        $this->assertArrayNotHasKey('validation.ascii', $rows);
        $this->assertSame('Next / Previous page buttons under lists', $rows['pagination.next'][4]);
    }

    public function test_pages_and_catalogue_names_are_in_the_sheet(): void
    {
        $rows = $this->export();

        $this->assertSame('Ammaan ahaw', $rows['pages.safety.title'][2]);
        $this->assertSame('pages-safety.png', $rows['pages.safety.title'][5]);
        $this->assertStringStartsWith('Page /safety', $rows['pages.safety.body'][4]);
        // No picture for a page we did not take.
        $this->assertSame('', $rows['pages.terms.title'][5]);
        $this->assertSame(['catalogue.Cars', 'Cars', 'Baabuur', 'needs native review'], array_slice($rows['catalogue.Cars'], 0, 4));
        $this->assertSame('Muqdisho', $rows['catalogue.Mogadishu'][2]);
        $this->assertSame(['help.post-an-ad.title', 'How to post an ad', 'Sida loo dhigo xayeysiis', 'needs native review'], array_slice($rows['help.post-an-ad.title'], 0, 4));
        $this->assertStringContainsString(':max_photos', $rows['help.post-an-ad.body'][2]);
    }

    public function test_review_sheet_in_docs_is_up_to_date(): void
    {
        $this->artisan('iibsom:translations:export', [
            'locale' => 'so',
            '--path' => $this->csv,
            '--screens' => base_path('docs/somali-review/screens'),
        ])->assertSuccessful();

        $this->assertFileEquals(
            base_path('docs/somali-review/somali-texts.csv'),
            $this->csv,
            'Texts changed: run the command in docs/somali-review/README.md to rebuild the sheet.',
        );
    }

    public function test_every_english_text_is_used_somewhere(): void
    {
        $rows = $this->export();

        $unused = array_keys(array_filter($rows, fn ($r) => str_starts_with($r[4], '(not found')));
        $this->assertNotEmpty($rows);
        $this->assertSame([], $unused, 'Texts nobody shows: remove them from lang/en and lang/so.');
    }

    public function test_english_and_somali_have_the_same_keys(): void
    {
        foreach (File::files(lang_path('en')) as $file) {
            $group = $file->getFilenameWithoutExtension();
            $en = array_keys(Arr::dot(require $file->getPathname()));
            $so = array_keys(Arr::dot(require lang_path("so/{$group}.php")));
            sort($en);
            sort($so);
            $this->assertSame($en, $so, "lang/so/{$group}.php must have exactly the keys of lang/en/{$group}.php");
        }
    }

    public function test_unknown_language_fails(): void
    {
        $this->artisan('iibsom:translations:export', ['locale' => 'xx'])->assertFailed();
    }
}
