<?php

use App\Models\Category;
use App\Support\Ads\AdCounters;
use App\Support\Catalog;
use Database\Seeders\CategorySeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * PR 93 (owner: "keep ours and add theirs"): about 70 more sub categories, 2 new main categories (Commercial
 * Equipment, Business & Industry) and a "Field of work" filter for jobs, on sites that already exist (live and
 * demo). Only ADDS what is missing: names, order, icons, parents and on/off switches an admin set stay; no ad moves;
 * no address changes. New ones go to the end of their level (the admin can reorder them in Categories).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Category::query()->exists()) {
            return; // a new site: the normal seeder makes the whole tree
        }
        (new CategorySeeder)->addMissing();
        AdCounters::refreshAll();
        app(Catalog::class)->flush();
    }

    public function down(): void
    {
        // Nothing to undo safely: new categories may already have ads.
    }
};
