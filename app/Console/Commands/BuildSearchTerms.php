<?php

namespace App\Console\Commands;

use App\Support\Search\SearchTerms;
use Illuminate\Console\Command;

class BuildSearchTerms extends Command
{
    protected $signature = 'iibsom:search-terms';

    protected $description = 'Rebuild the words offered while typing a search (from live ad titles)';

    public function handle(): int
    {
        $this->info('Search terms: '.SearchTerms::rebuild());

        return self::SUCCESS;
    }
}
