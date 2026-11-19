<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PR 68: why the seller closed the ad (switch "ads.close_reason") and the last free "move to top" (switch "ads.free_bump").
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->string('closed_reason', 20)->nullable()->after('sold_at');
            $table->timestamp('bumped_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropColumn(['closed_reason', 'bumped_at']);
        });
    }
};
