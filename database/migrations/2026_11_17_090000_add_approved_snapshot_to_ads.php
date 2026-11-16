<?php

use App\Support\Ads\AdSnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            // The last version an admin approved: lets the queue show "Edited" + what changed.
            $table->json('approved_snapshot')->nullable()->after('reviewed_at');
        });

        // Ads approved before this existed: their current text IS the approved version
        // (except ads waiting again after an edit: for those we can't know, so no snapshot).
        AdSnapshot::backfill();
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropColumn('approved_snapshot');
        });
    }
};
