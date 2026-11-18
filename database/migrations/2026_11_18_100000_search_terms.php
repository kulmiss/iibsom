<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Search suggestions (PR 63): words and two-word phrases from live ad titles, with how many ads use them.
// Rebuilt by `php artisan iibsom:search-terms` (every night). Nothing about visitors is stored here.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_terms', function (Blueprint $table) {
            $table->id();
            // Binary collation: "cafe" and "café" are two terms (a normal collation would call them the same and
            // refuse the second). Terms are always stored lowercase. The unique index also serves "starts with" lookups.
            $table->string('term', 60)->collation('utf8mb4_bin')->unique();
            $table->unsignedInteger('ads');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_terms');
    }
};
