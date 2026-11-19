<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Speed check at 100k ads (PR 71): "Popular now" (most viewed live ads) read 49,000 rows and sorted them
// (64 ms when not cached); with this index it reads the most viewed first (15 ms).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->index(['status', 'views']);
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropIndex(['status', 'views']);
        });
    }
};
