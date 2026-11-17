<?php

namespace App\Console\Commands;

use App\Support\Demo\DemoData;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

/** Fresh demo data in one step. Refuses to run on the live site. */
class DemoReset extends Command
{
    protected $signature = 'iib:demo-reset {--no-photos : Faster, ads without photos} {--force : Do not ask}';

    protected $description = 'Delete all demo data and create it again (never in production)';

    public function handle(): int
    {
        if ($this->laravel->isProduction()) {
            $this->error('Refused: this is production (APP_ENV=production). Demo data is for local and test servers only.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Delete ALL demo data and make new demo data?', true)) {
            return self::SUCCESS;
        }

        $deleted = DemoData::deleteAll();
        $this->line("Deleted {$deleted['ads']} demo ads, {$deleted['users']} demo users, {$deleted['reports']} reports.");

        $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        $seeder = $this->laravel->make(DemoSeeder::class);
        $seeder->withPhotos = ! $this->option('no-photos');
        $seeder->setContainer($this->laravel)->setCommand($this);
        $this->info('Making demo data'.($seeder->withPhotos ? ' with photos (can take a few minutes)…' : '…'));
        $seeder->__invoke();

        $this->newLine();
        $this->info('Done. Sign in to /admin with the demo staff above (add the 2FA key to an authenticator app).');

        return self::SUCCESS;
    }
}
