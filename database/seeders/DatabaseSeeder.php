<?php

namespace Database\Seeders;

use App\Support\Catalog;
use Illuminate\Database\Seeder;

/**
 * Real starting data, safe for production: Somalia locations + categories.
 * (Demo ads come later in a separate DemoSeeder that refuses to run in production.)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            LocationSeeder::class,
            CategorySeeder::class,
            PageSeeder::class,
        ]);

        app(Catalog::class)->flush();
    }
}
