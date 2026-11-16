<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

/**
 * Review sheet for a native speaker:
 *   php artisan iibsom:translations:export so
 * Writes storage/app/translations-so.csv with columns:
 *   key, english, translation, status
 * Status = missing | needs native review | reviewed
 */
class ExportTranslations extends Command
{
    protected $signature = 'iibsom:translations:export {locale : Language code, e.g. so} {--path= : Where to save the CSV}';

    protected $description = 'Export texts to a CSV so a native speaker can review the translation';

    public function handle(): int
    {
        $locale = $this->argument('locale');

        if (! array_key_exists($locale, config('iibsom.locales'))) {
            $this->error("Unknown language [{$locale}]. Add it to config/iibsom.php first.");

            return self::FAILURE;
        }

        $reviewedFile = lang_path("{$locale}/_reviewed.php");
        $reviewed = File::exists($reviewedFile) ? (array) require $reviewedFile : [];

        $rows = [];
        foreach (File::files(lang_path('en')) as $file) {
            $group = $file->getFilenameWithoutExtension();
            $english = Arr::dot(require $file->getPathname());
            $targetFile = lang_path("{$locale}/{$group}.php");
            $target = File::exists($targetFile) ? Arr::dot(require $targetFile) : [];

            foreach ($english as $key => $text) {
                $fullKey = "{$group}.{$key}";
                $translation = $target[$key] ?? '';
                $status = match (true) {
                    $translation === '' => 'missing',
                    in_array($fullKey, $reviewed, true) => 'reviewed',
                    default => 'needs native review',
                };
                $rows[] = [$fullKey, $text, $translation, $status];
            }
        }

        $path = $this->option('path') ?: storage_path("app/translations-{$locale}.csv");
        File::ensureDirectoryExists(dirname($path));

        $handle = fopen($path, 'w');
        fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Somali letters correctly
        fputcsv($handle, ['key', 'english', 'translation', 'status'], escape: '');
        foreach ($rows as $row) {
            fputcsv($handle, $row, escape: '');
        }
        fclose($handle);

        $counts = array_count_values(array_column($rows, 3));
        $this->info("Saved {$path}");
        foreach ($counts as $status => $count) {
            $this->line("  {$status}: {$count}");
        }

        return self::SUCCESS;
    }
}
