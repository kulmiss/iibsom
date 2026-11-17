<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// "Most viewed" and the "Popular" label read ads.views (added in PR 52, starting at 0).
// Sites that already had "Ad statistics" ON keep their history: fill it from the daily numbers once.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ad_stats')->select('ad_id', DB::raw('SUM(views) AS total'))->groupBy('ad_id')
            ->orderBy('ad_id')->chunk(1000, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('ads')->where('id', $row->ad_id)->update(['views' => (int) $row->total]);
                }
            });
    }

    public function down(): void
    {
        // Nothing to undo: the numbers stay correct.
    }
};
