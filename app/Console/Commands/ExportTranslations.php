<?php

namespace App\Console\Commands;

use App\Support\Help\HelpCentre;
use App\Support\Translations\ReviewRules;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Review sheet for a native speaker:
 *   php artisan iibsom:translations:export so
 * Writes storage/app/translations-{locale}.csv with columns:
 *   key, english, translation, status, where, screenshot
 * status     = missing | needs native review | reviewed
 * where      = the files that show this text
 * screenshot = the picture (in --screens) of the page that shows it, when there is one
 * Also lists the core pages, the help articles and the catalogue names.
 */
class ExportTranslations extends Command
{
    protected $signature = 'iibsom:translations:export
        {locale : Language code, e.g. so}
        {--path= : Where to save the CSV}
        {--screens= : Folder with page pictures (from tools/somali-screenshots)}
        {--review : The reviewer\'s layout (PR 112): key, screen, english, somali, max_length, notes}';

    protected $description = 'Export texts to a CSV so a native speaker can review the translation';

    /** Folders searched to find where each text is used. */
    private const SOURCE_DIRS = ['resources/views', 'resources/js', 'app', 'routes', 'config'];

    /** Where Laravel's own texts show up. */
    private const LARAVEL_WHERE = [
        'validation' => 'Error messages under form fields',
        'pagination' => 'Next / Previous page buttons under lists',
    ];

    /** Small parts that show inside another page's picture. */
    private const PART_OF_PAGE = [
        'ads/_card' => 'browse-index.png',
        'pages/show' => 'pages-safety.png',
        'help/show' => 'help-post-an-ad.png',
        'help/_contact' => 'help-index.png',
        'partials/welcome-tour' => 'tour.png',
    ];

    private const NOT_FOUND = '(not found: the key may be built from parts in the code)';

    public function handle(): int
    {
        $locale = (string) $this->argument('locale');

        if (! array_key_exists($locale, config()->array('iibsom.locales'))) {
            $this->error("Unknown language [{$locale}]. Add it to config/iibsom.php first.");

            return self::FAILURE;
        }

        $reviewedFile = lang_path("{$locale}/_reviewed.php");
        $reviewed = File::exists($reviewedFile) ? (array) require $reviewedFile : [];

        $screensDir = $this->option('screens');
        $screens = is_string($screensDir) && File::isDirectory($screensDir)
            ? array_values(array_map(fn ($f) => $f->getFilename(), File::files($screensDir)))
            : [];

        [$exact, $prefixes] = $this->usages();

        $rows = [];
        foreach ($this->groups($locale) as $group) {
            $target = $this->texts($group, $locale);
            $laravelGroup = ! File::exists(lang_path("en/{$group}.php"));
            // Laravel's own groups have hundreds of messages; we list only the ones we translated.
            $english = $laravelGroup
                ? array_intersect_key($this->texts($group, 'en'), $target)
                : $this->texts($group, 'en');

            foreach ($english as $key => $text) {
                $fullKey = "{$group}.{$key}";
                $translation = $target[$key] ?? '';
                $status = match (true) {
                    $translation === '' => 'missing',
                    in_array($fullKey, $reviewed, true) => 'reviewed',
                    default => 'needs native review',
                };
                $files = $laravelGroup ? [] : $this->whereUsed($fullKey, $exact, $prefixes);
                $where = match (true) {
                    $laravelGroup => self::LARAVEL_WHERE[$group] ?? 'Laravel messages',
                    $files === [] => self::NOT_FOUND,
                    default => implode(' | ', $files),
                };
                $rows[] = [
                    $fullKey,
                    $text,
                    $translation,
                    $status,
                    $where,
                    implode(' | ', $this->screenshotsFor($files, $screens)),
                ];
            }
        }

        foreach ($this->dataTexts($locale) as [$key, $text, $translation, $where, $picture]) {
            $rows[] = [
                $key,
                $text,
                $translation,
                match (true) {
                    $translation === '' => 'missing',
                    in_array($key, $reviewed, true) => 'reviewed',
                    default => 'needs native review',
                },
                $where,
                in_array($picture, $screens, true) ? $picture : '',
            ];
        }

        $path = $this->option('path') ?: storage_path("app/translations-{$locale}.csv");
        File::ensureDirectoryExists(dirname((string) $path));

        $handle = @fopen((string) $path, 'w');
        if ($handle === false) {
            $this->error("Could not write {$path}. Check that the folder exists and can be written.");

            return self::FAILURE;
        }
        fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Somali letters correctly
        if ($this->option('review')) {
            // PR 112: the layout the owner asked for. "screen" = the picture, or where the text shows when there is none.
            fputcsv($handle, ['key', 'screen', 'english', 'somali', 'max_length', 'notes'], escape: '');
            foreach ($rows as [$key, $english, $somali, $status, $where, $picture]) {
                fputcsv($handle, [$key, $picture !== '' ? $picture : $where, $english, $somali, ReviewRules::maxLength($key, $english), ReviewRules::note($key, $english, $status)], escape: '');
            }
        } else {
            fputcsv($handle, ['key', 'english', 'translation', 'status', 'where', 'screenshot'], escape: '');
            foreach ($rows as $row) {
                fputcsv($handle, $row, escape: '');
            }
        }
        fclose($handle);

        $counts = array_count_values(array_column($rows, 3));
        $this->info("Saved {$path}");
        foreach ($counts as $status => $count) {
            $this->line("  {$status}: {$count}");
        }

        return self::SUCCESS;
    }

    /**
     * Our own text files, plus Laravel's files that exist only in the target
     * language (validation, pagination): their English comes from Laravel.
     *
     * @return list<string>
     */
    private function groups(string $locale): array
    {
        $names = fn (string $dir) => File::isDirectory($dir)
            ? array_map(fn ($f) => $f->getFilenameWithoutExtension(), File::files($dir))
            : [];
        $groups = array_unique(array_merge($names(lang_path('en')), $names(lang_path($locale))));
        $groups = array_values(array_filter($groups, fn ($g) => ! str_starts_with($g, '_')));
        sort($groups);

        return $groups;
    }

    /** @return array<string, string> */
    private function texts(string $group, string $locale): array
    {
        $file = lang_path("{$locale}/{$group}.php");
        $lines = File::exists($file)
            ? (array) require $file
            : (array) trans($group, [], $locale);

        // Laravel's own groups (validation, pagination) have no English file here: ask Laravel.
        $flat = Arr::dot($lines);

        return array_filter(array_map(fn ($v) => is_string($v) ? $v : '', $flat), fn ($v) => $v !== '');
    }

    /**
     * Texts kept outside lang/: the core pages, the help articles and the
     * catalogue names (categories, fields, places). Only Somali has them for now.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    private function dataTexts(string $locale): array
    {
        if ($locale !== 'so') {
            return [];
        }

        $rows = [];
        $pages = require database_path('seeders/data/pages.php');
        foreach ($pages['en'] as $slug => [$title, $body]) {
            $where = "Page /{$slug} (starter text; admin > Pages can change it)";
            $rows[] = ["pages.{$slug}.title", $title, $pages['so'][$slug][0] ?? '', $where, "pages-{$slug}.png"];
            $rows[] = ["pages.{$slug}.body", $body, $pages['so'][$slug][1] ?? '', $where, "pages-{$slug}.png"];
        }

        $english = HelpCentre::raw('en');
        foreach (HelpCentre::raw($locale) as $slug => $article) {
            $where = "Help page /help/{$slug}";
            $rows[] = ["help.{$slug}.title", $english[$slug]['title'] ?? '', $article['title'], $where, 'help-index.png'];
            $rows[] = ["help.{$slug}.body", $english[$slug]['body'] ?? '', $article['body'], $where, "help-{$slug}.png"];
        }

        $names = require database_path('seeders/data/somali.php');
        foreach ($names as $english => $somali) {
            $rows[] = [
                "catalogue.{$english}",
                $english,
                $somali,
                'Category, field and place names (starter list; admin can change them)',
                '',
            ];
        }

        return $rows;
    }

    /**
     * Every quoted "group.key" in the code, and every quoted "group.part." prefix
     * (code that adds the last part itself, like __('site.categories.'.$slug)).
     *
     * @return array{0: array<string, list<string>>, 1: array<string, list<string>>}
     */
    private function usages(): array
    {
        $exact = [];
        $prefixes = [];
        foreach (self::SOURCE_DIRS as $dir) {
            if (! File::isDirectory(base_path($dir))) {
                continue;
            }
            foreach (File::allFiles(base_path($dir)) as $file) {
                $relative = $dir.'/'.str_replace('\\', '/', $file->getRelativePathname());
                preg_match_all('/([\'"])([a-z_]+(?:\.[A-Za-z0-9_-]+)*\.?)\1/', $file->getContents(), $m);
                foreach (array_unique($m[2]) as $string) {
                    if (! str_contains($string, '.')) {
                        continue;
                    }
                    if (str_ends_with($string, '.')) {
                        $prefixes[$string][] = $relative;
                    } else {
                        $exact[$string][] = $relative;
                    }
                }
            }
        }

        return [$exact, $prefixes];
    }

    /**
     * @param  array<string, list<string>>  $exact
     * @param  array<string, list<string>>  $prefixes
     * @return list<string>
     */
    private function whereUsed(string $key, array $exact, array $prefixes): array
    {
        $files = $exact[$key] ?? [];
        foreach ($prefixes as $prefix => $found) {
            if (str_starts_with($key, $prefix)) {
                $files = array_merge($files, $found);
            }
        }
        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }

    /**
     * Page picture for each view: resources/views/account/my-ads.blade.php → account-my-ads.png.
     * Header, footer and menus show on every page, so they point to home.png;
     * small parts (like the ad card) point to the page they sit in.
     *
     * @param  list<string>  $files
     * @param  list<string>  $screens
     * @return list<string>
     */
    private function screenshotsFor(array $files, array $screens): array
    {
        $found = [];
        foreach ($files as $file) {
            if (! str_starts_with($file, 'resources/views/')) {
                continue;
            }
            $view = Str::between($file, 'resources/views/', '.blade.php');
            $name = match (true) {
                isset(self::PART_OF_PAGE[$view]) => self::PART_OF_PAGE[$view],
                str_starts_with($view, 'layouts/'), str_starts_with($view, 'partials/') => 'home.png',
                default => str_replace('/', '-', $view).'.png',
            };
            if (in_array($name, $screens, true)) {
                $found[] = $name;
            }
        }

        return array_values(array_unique($found));
    }
}
