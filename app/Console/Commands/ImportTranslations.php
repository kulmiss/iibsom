<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\City;
use App\Models\Region;
use App\Support\Translations\LangFileWriter;
use App\Support\Translations\ReviewRules;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Reads the Somali review sheet back (PR 112):
 *   php artisan iibsom:translations:import somali-review.csv --dry-run   (shows what would change, writes nothing)
 *   php artisan iibsom:translations:import somali-review.csv             (writes the changes)
 *
 * Safe:
 * - every changed text is checked: the words the site fills in (:name, :count), the plural parts, the length, no HTML;
 * - if ANY row has a problem, nothing is written (fix the sheet and run it again);
 * - unknown keys and repeated keys are problems; rows missing from the sheet are only listed;
 * - an empty Somali cell keeps the text that is on the site now.
 * Screen texts go into lang/{locale}/*.php (comments and order stay); names of categories, fields, cities and
 * districts go into database/seeders/data/somali.php and into the database where the admin did not change them.
 * Help articles and the 4 pages are listed to change by hand (Admin → Pages, resources/help/so).
 * Every text in the sheet with a Somali text counts as checked (lang/{locale}/_reviewed.php; districts lose "check names").
 */
class ImportTranslations extends Command
{
    protected $signature = 'iibsom:translations:import
        {file : The reviewed CSV}
        {--locale=so : Language code}
        {--dry-run : Only show what would change}
        {--names-file= : (tests) the catalogue names file}';

    protected $description = 'Import a reviewed translation sheet back into the site, with checks';

    public function handle(): int
    {
        $locale = (string) $this->option('locale');
        if (! array_key_exists($locale, config()->array('iibsom.locales')) || $locale === config('iibsom.default_locale')) {
            $this->error("Unknown language [{$locale}].");

            return self::FAILURE;
        }
        $rows = $this->readCsv((string) $this->argument('file'));
        if ($rows === null) {
            return self::FAILURE;
        }

        $namesFile = (string) ($this->option('names-file') ?: database_path('seeders/data/somali.php'));
        $names = File::exists($namesFile) ? (array) require $namesFile : [];
        [$english, $current] = $this->langTexts($locale);

        $changes = [];      // group => [key => text]
        $nameChanges = [];  // English name => Somali
        $checked = [];      // keys that count as reviewed
        $problems = [];
        $skipped = [];
        $unchanged = 0;
        $seen = [];

        foreach ($rows as $line => $row) {
            $key = $row['key'];
            $somali = $row['somali'];
            if (isset($seen[$key])) {
                $problems[] = "line {$line}: {$key} — repeated (also on line {$seen[$key]})";

                continue;
            }
            $seen[$key] = $line;
            if ($somali === '') {
                continue; // empty: keep the text on the site
            }

            if (str_starts_with($key, 'help.') || str_starts_with($key, 'pages.')) {
                $skipped[] = $key;

                continue;
            }

            if (str_starts_with($key, 'catalogue.')) {
                $name = substr($key, strlen('catalogue.'));
                if (! array_key_exists($name, $names)) {
                    $problems[] = "line {$line}: {$key} — unknown name";

                    continue;
                }
                $checked[] = $key;
                if ($somali === $names[$name]) {
                    $unchanged++;

                    continue;
                }
                if ($found = ReviewRules::problems($key, $name, $somali, $row['max'])) {
                    $problems[] = "line {$line}: {$key} — ".implode('; ', $found);

                    continue;
                }
                $nameChanges[$name] = $somali;

                continue;
            }

            if (! array_key_exists($key, $english)) {
                $problems[] = "line {$line}: {$key} — unknown key";

                continue;
            }
            $checked[] = $key;
            if ($somali === ($current[$key] ?? '')) {
                $unchanged++;

                continue;
            }
            if ($found = ReviewRules::problems($key, $english[$key], $somali, $row['max'])) {
                $problems[] = "line {$line}: {$key} — ".implode('; ', $found);

                continue;
            }
            [$group, $inner] = explode('.', $key, 2);
            $changes[$group][$inner] = $somali;
        }

        $missing = array_diff(array_merge(array_keys($english), array_map(fn ($n) => 'catalogue.'.$n, array_keys($names))), array_keys($seen));
        $changedCount = array_sum(array_map('count', $changes)) + count($nameChanges);

        $this->info('Rows: '.count($rows).". Changed: {$changedCount}. Same as now: {$unchanged}. To change by hand (help, pages): ".count($skipped).'.');
        if ($missing !== []) {
            $this->warn(count($missing).' texts are not in the sheet (they stay as they are), e.g. '.implode(', ', array_slice(array_values($missing), 0, 5)));
        }
        foreach ($skipped as $key) {
            $this->line("  by hand: {$key}");
        }
        foreach ($changes as $group => $texts) {
            foreach ($texts as $inner => $text) {
                $this->line("  change {$group}.{$inner}: ".mb_strimwidth(($current["{$group}.{$inner}"] ?? ''), 0, 40, '…').' → '.mb_strimwidth($text, 0, 40, '…'));
            }
        }
        foreach ($nameChanges as $name => $text) {
            $this->line("  change name {$name}: {$names[$name]} → {$text}");
        }

        if ($problems !== []) {
            $this->error(count($problems).' problems. Nothing was written. Fix these rows and run it again:');
            foreach ($problems as $problem) {
                $this->line("  {$problem}");
            }

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: nothing was written.');

            return self::SUCCESS;
        }

        try {
            $written = $this->writeLang($locale, $changes);
            $this->writeNames($namesFile, $nameChanges);
            $this->updateDatabase($locale, $nameChanges, $names, $checked);
            $this->markReviewed($locale, $checked);
        } catch (Throwable $e) {
            $this->error('Could not write: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Done. Files changed: {$written}. Checked texts: ".count($checked).'.');

        return self::SUCCESS;
    }

    /** @return array<int, array{key: string, somali: string, max: int|null}>|null line number => row */
    private function readCsv(string $file): ?array
    {
        if (! is_file($file) || ($handle = fopen($file, 'r')) === false) {
            $this->error("Cannot open {$file}.");

            return null;
        }
        $header = fgetcsv($handle, 0, ',', '"', '');
        $header = array_map(fn ($h) => strtolower(trim(str_replace("\xEF\xBB\xBF", '', (string) $h))), $header ?: []);
        $keyCol = array_search('key', $header, true);
        $somaliCol = array_search('somali', $header, true);
        if ($somaliCol === false) {
            $somaliCol = array_search('translation', $header, true);
        }
        $maxCol = array_search('max_length', $header, true);
        if ($keyCol === false || $somaliCol === false) {
            $this->error('The sheet needs a "key" column and a "somali" (or "translation") column.');
            fclose($handle);

            return null;
        }

        $rows = [];
        $line = 1;
        while (($data = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $line++;
            $key = trim((string) ($data[$keyCol] ?? ''));
            if ($key === '') {
                continue;
            }
            $max = $maxCol !== false && is_numeric($data[$maxCol] ?? null) ? (int) $data[$maxCol] : null;
            $rows[$line] = ['key' => $key, 'somali' => trim(str_replace("\r\n", "\n", (string) ($data[$somaliCol] ?? ''))), 'max' => $max];
        }
        fclose($handle);

        return $rows;
    }

    /** @return array{0: array<string, string>, 1: array<string, string>} English and current texts by full key */
    private function langTexts(string $locale): array
    {
        $english = [];
        $current = [];
        foreach (File::files(lang_path($locale)) as $file) {
            $group = $file->getFilenameWithoutExtension();
            if (str_starts_with($group, '_')) {
                continue;
            }
            $target = Arr::dot((array) require $file->getPathname());
            $laravelGroup = ! File::exists(lang_path("en/{$group}.php")); // validation, pagination: English from Laravel
            $source = $laravelGroup ? Arr::dot((array) trans($group, [], 'en')) : Arr::dot((array) require lang_path("en/{$group}.php"));
            foreach ($source as $key => $text) {
                if (! is_string($text) || ($laravelGroup && ! array_key_exists($key, $target))) {
                    continue; // Laravel has hundreds of messages: only the ones we translated
                }
                $english["{$group}.{$key}"] = $text;
                $current["{$group}.{$key}"] = is_string($target[$key] ?? null) ? $target[$key] : '';
            }
        }

        return [$english, $current];
    }

    /** @param  array<string, array<string, string>>  $changes */
    private function writeLang(string $locale, array $changes): int
    {
        // First check every file, then write them all (never half the changes).
        $ready = [];
        foreach ($changes as $group => $texts) {
            $path = lang_path("{$locale}/{$group}.php");
            $result = LangFileWriter::apply((string) File::get($path), $texts);
            if ($result['refused'] !== []) {
                throw new \RuntimeException("{$group}: could not change ".implode(', ', $result['refused']).' (built from parts: change by hand).');
            }
            $ready[$path] = $result['code'];
        }
        foreach ($ready as $path => $code) {
            File::put($path, $code);
        }

        return count($ready);
    }

    /** @param  array<string, string>  $nameChanges */
    private function writeNames(string $namesFile, array $nameChanges): void
    {
        if ($nameChanges === []) {
            return;
        }
        $result = LangFileWriter::apply((string) File::get($namesFile), $nameChanges);
        if ($result['refused'] !== []) {
            throw new \RuntimeException('names: could not change '.implode(', ', $result['refused']));
        }
        File::put($namesFile, $result['code']);
    }

    /**
     * Names in the database: only where the Somali name is still our starter text (an admin's change is kept).
     *
     * @param  array<string, string>  $nameChanges
     * @param  array<string, string>  $old
     * @param  list<string>  $checked
     */
    private function updateDatabase(string $locale, array $nameChanges, array $old, array $checked): void
    {
        /** @var list<class-string<Model>> $models */
        $models = [Category::class, Region::class, City::class, Area::class];
        foreach ($models as $model) {
            foreach ($model::query()->get() as $row) {
                $name = (array) $row->getAttribute('name');
                $en = (string) ($name['en'] ?? '');
                if (isset($nameChanges[$en]) && ($name[$locale] ?? null) === ($old[$en] ?? null)) {
                    $name[$locale] = $nameChanges[$en];
                    $row->setAttribute('name', $name);
                }
                if ($row instanceof Area && in_array('catalogue.'.$en, $checked, true)) {
                    $row->needs_review = false;
                }
                if ($row->isDirty()) {
                    $row->save();
                }
            }
        }
        foreach (CategoryField::query()->get() as $field) {
            $label = (array) $field->label;
            $en = (string) ($label['en'] ?? '');
            if (isset($nameChanges[$en]) && ($label[$locale] ?? null) === ($old[$en] ?? null)) {
                $label[$locale] = $nameChanges[$en];
                $field->label = $label;
                $field->save();
            }
        }
    }

    /** @param  list<string>  $checked */
    private function markReviewed(string $locale, array $checked): void
    {
        $file = lang_path("{$locale}/_reviewed.php");
        $before = File::exists($file) ? (array) require $file : [];
        $all = array_values(array_unique(array_merge($before, $checked)));
        sort($all);
        $body = "<?php\n\n// Keys (like \"site.home.title\") a native Somali speaker has checked.\n// Anything not listed here shows as \"needs native review\" in the CSV.\n// Filled by: php artisan iibsom:translations:import (PR 112).\n\nreturn ".
            str_replace(["array (\n", "\n)"], ["[\n", "\n]"], var_export($all, true)).";\n";
        File::put($file, preg_replace('/^  \d+ => /m', '    ', $body) ?? $body);
    }
}
