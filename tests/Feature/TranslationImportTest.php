<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Support\Translations\LangFileWriter;
use App\Support\Translations\ReviewRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

/** PR 112: the Somali review sheet goes out (review layout) and comes back in safely. */
class TranslationImportTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private string $names;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        // Work on copies: the real files are never touched by these tests.
        $this->dir = sys_get_temp_dir().'/iibsom-lang-'.uniqid();
        File::copyDirectory(base_path('lang'), $this->dir);
        $this->app->useLangPath($this->dir);
        $this->names = $this->dir.'/somali-names.php';
        File::copy(database_path('seeders/data/somali.php'), $this->names);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /** @return list<array<int, string>> the review sheet, as rows */
    private function sheet(): array
    {
        $path = $this->dir.'/review.csv';
        $this->artisan('iibsom:translations:export', ['locale' => 'so', '--review' => true, '--path' => $path])->assertSuccessful();
        $rows = array_map(fn ($l) => str_getcsv($l, ',', '"', ''), []);
        $handle = fopen($path, 'r');
        while (($r = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rows[] = $r;
        }
        fclose($handle);
        $rows[0][0] = str_replace("\xEF\xBB\xBF", '', $rows[0][0]);

        return $rows;
    }

    /** @param  list<array<int, string>>  $rows */
    private function save(array $rows): string
    {
        $path = $this->dir.'/reviewed.csv';
        $handle = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row, escape: '');
        }
        fclose($handle);

        return $path;
    }

    /** @param  list<array<int, string>>  $rows */
    private function set(array &$rows, string $key, string $somali): void
    {
        foreach ($rows as $i => $row) {
            if ($row[0] === $key) {
                $rows[$i][3] = $somali;

                return;
            }
        }
        $this->fail("{$key} not in the sheet");
    }

    private function import(string $path, bool $dry = false): PendingCommand
    {
        return $this->artisan('iibsom:translations:import', ['file' => $path, '--names-file' => $this->names, '--dry-run' => $dry]);
    }

    public function test_review_sheet_has_the_owners_columns_and_categories_and_districts(): void
    {
        $rows = $this->sheet();
        $this->assertSame(['key', 'screen', 'english', 'somali', 'max_length', 'notes'], $rows[0]);
        $keys = array_column($rows, 0);
        foreach (['account.login.title', 'catalogue.Hodan', 'catalogue.Hargeisa', 'catalogue.Gacan Libah', 'catalogue.Cars', 'scam.title'] as $key) {
            $this->assertContains($key, $keys);
        }
        $byKey = array_column($rows, null, 0);
        $this->assertSame('60', $byKey['catalogue.Hodan'][4]);
        $this->assertStringContainsString('check the spelling', $byKey['catalogue.Hodan'][5]);
        $this->assertStringContainsString('keep :count', $byKey['ads.browse.results'][5]);
    }

    public function test_dry_run_shows_changes_and_writes_nothing(): void
    {
        $rows = $this->sheet();
        $this->set($rows, 'account.login.title', 'Soo gal');
        $before = File::get($this->dir.'/so/account.php');

        $this->import($this->save($rows), true)->expectsOutputToContain('Changed: 1')->expectsOutputToContain('Dry run')->assertSuccessful();
        $this->assertSame($before, File::get($this->dir.'/so/account.php'));
    }

    public function test_import_writes_texts_keeps_comments_and_marks_them_checked(): void
    {
        $rows = $this->sheet();
        $this->set($rows, 'account.login.title', "Soo gal 'hadda'");
        $this->set($rows, 'ads.browse.results', '{0} Wax xayeysiis ah lama helin|{1} Hal xayeysiis|[2,*] :count xayeysiis');
        $this->set($rows, 'catalogue.Hodan', 'Hoodan');
        $comments = substr_count(File::get($this->dir.'/so/ads.php'), '//');

        $this->import($this->save($rows))->assertSuccessful();

        $account = require $this->dir.'/so/account.php';
        $this->assertSame("Soo gal 'hadda'", $account['login']['title']);
        $ads = require $this->dir.'/so/ads.php';
        $this->assertSame('{0} Wax xayeysiis ah lama helin|{1} Hal xayeysiis|[2,*] :count xayeysiis', $ads['browse']['results']);
        $this->assertSame($comments, substr_count(File::get($this->dir.'/so/ads.php'), '//')); // comments kept
        $this->assertSame('Hoodan', (require $this->names)['Hodan']);
        $hodan = Area::where('slug', 'hodan')->firstOrFail();
        $this->assertSame('Hoodan', $hodan->name['so']);
        $this->assertFalse($hodan->needs_review);

        $reviewed = require $this->dir.'/so/_reviewed.php';
        $this->assertContains('account.login.title', $reviewed);
        $this->assertContains('catalogue.Hodan', $reviewed);
        $this->assertContains('site.nav.home', $reviewed); // unchanged rows in the sheet count as checked
        $this->assertSame('Soo gal \'hadda\'', __('account.login.title', [], 'so'));
    }

    public function test_any_problem_stops_the_whole_import(): void
    {
        $rows = $this->sheet();
        $this->set($rows, 'account.login.title', 'Soo gal'); // fine on its own
        $this->set($rows, 'ads.browse.results', '{0} Lama helin|[2,*] xayeysiis'); // lost :count and a plural part
        $this->set($rows, 'site.nav.home', str_repeat('Guri ', 20)); // too long
        $this->set($rows, 'account.login.heading', 'Salaan <b>x</b>');
        $rows[] = ['ads.no_such_key', '', 'x', 'y', '30', ''];
        $rows[] = ['account.login.title', '', 'Sign in', 'Gal', '30', '']; // repeated
        $before = File::get($this->dir.'/so/account.php');

        $this->import($this->save($rows))
            ->expectsOutputToContain('Nothing was written')
            ->expectsOutputToContain('missing :count')
            ->expectsOutputToContain('too long')
            ->expectsOutputToContain('HTML is not allowed')
            ->expectsOutputToContain('unknown key')
            ->expectsOutputToContain('repeated')
            ->assertFailed();
        $this->assertSame($before, File::get($this->dir.'/so/account.php'));
    }

    public function test_empty_cells_keep_the_text_and_missing_rows_are_listed(): void
    {
        $rows = $this->sheet();
        $this->set($rows, 'account.login.title', '');
        $rows = array_values(array_filter($rows, fn ($r) => $r[0] !== 'account.login.heading'));
        $this->import($this->save($rows))->expectsOutputToContain('not in the sheet')->assertSuccessful();
        $this->assertSame('Gal', (require $this->dir.'/so/account.php')['login']['title']);
    }

    public function test_a_sheet_without_the_right_columns_is_refused(): void
    {
        $path = $this->save([['name', 'text'], ['a', 'b']]);
        $this->import($path)->expectsOutputToContain('needs a "key" column')->assertFailed();
        $this->import($this->dir.'/nope.csv')->assertFailed();
    }

    public function test_writer_changes_only_plain_texts_and_keeps_the_rest(): void
    {
        $code = "<?php\n\n// top\nreturn [\n    'a' => 'one', // note\n    'b' => [\n        'c' => \"two\",\n        'd' => 'x'.'y',\n    ],\n];\n";
        $out = LangFileWriter::apply($code, ['a' => "it's", 'b.c' => 'deux', 'b.d' => 'z', 'nope' => 'n']);
        $this->assertSame(['a', 'b.c'], $out['done']);
        $this->assertEqualsCanonicalizing(['b.d', 'nope'], $out['refused']);
        $this->assertStringContainsString('// top', $out['code']);
        $this->assertStringContainsString('// note', $out['code']);
        $file = $this->dir.'/writer.php';
        File::put($file, $out['code']);
        $this->assertSame(['a' => "it's", 'b' => ['c' => 'deux', 'd' => 'xy']], require $file);
    }

    public function test_rules(): void
    {
        $this->assertSame([], ReviewRules::problems('x.y', 'Hello :name', 'Salaan :Name'));
        $this->assertNotSame([], ReviewRules::problems('x.y', 'Hello :name', 'Salaan'));
        $this->assertSame(30, ReviewRules::maxLength('x.y', 'Hi'));
        $this->assertSame(60, ReviewRules::maxLength('catalogue.Cars', 'Cars'));
    }
}
