<?php

namespace App\Console\Commands;

use App\Support\Search\AdFilters;
use Illuminate\Console\Command;

/** Fill the fast filter table again from every ad's details (PR 90). Safe to run any time. */
class RebuildAdFilters extends Command
{
    protected $signature = 'iibsom:filters:rebuild';

    protected $description = 'Rebuild the fast search filters (brand, model, year…) from every ad';

    public function handle(): int
    {
        $bar = $this->output->createProgressBar((int) \DB::table('ads')->count());
        $done = AdFilters::rebuildAll(fn (int $n) => $bar->advance($n));
        $bar->finish();
        $this->newLine();
        $this->info("Done: {$done} ads.");

        return self::SUCCESS;
    }
}
