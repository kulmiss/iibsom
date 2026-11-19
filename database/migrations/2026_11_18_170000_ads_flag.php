<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PR 72: why the system sent a live ad back for checking ("drugs": a word from the drug list), and which word.
// Staff see it in Ads (filter "Drugs"); approving the ad clears it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->string('flag', 20)->nullable()->after('closed_reason');
            $table->string('flag_word', 60)->nullable()->after('flag');
            $table->index(['flag', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropIndex(['flag', 'status']);
            $table->dropColumn(['flag', 'flag_word']);
        });
    }
};
