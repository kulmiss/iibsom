<?php

use Database\Seeders\LocationSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Districts (PR 111): names the owner's native reviewer has not checked yet are marked, so the admin shows them;
// and the new cities the owner asked for are added on sites that already run.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->boolean('needs_review')->default(false)->after('enabled');
        });
        // Every district so far came from our starting list: none was checked by a native speaker yet.
        DB::table('areas')->update(['needs_review' => true]);

        // Hargeisa and its districts, Burao, Berbera, Borama (owner, PR 111): only what is missing; new cities are OFF.
        // A new, empty site gets them from the normal seeder instead (with Mogadishu ON).
        if (DB::table('cities')->exists()) {
            (new LocationSeeder)->addMissing();
        }
    }

    public function down(): void
    {
        Schema::table('areas', fn (Blueprint $table) => $table->dropColumn('needs_review'));
    }
};
