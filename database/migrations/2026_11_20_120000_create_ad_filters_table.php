<?php

use App\Support\Search\AdFilters;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PR 90: ad detail values (brand, model, year…) in an indexed table for fast filters. Filled from ads.fields.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_filters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('text_value', 120)->nullable();
            $table->bigInteger('num_value')->nullable();
            $table->index(['key', 'text_value', 'ad_id']); // exact: brand = toyota
            $table->index(['key', 'num_value', 'ad_id']);  // range: year 2010–2015
        });

        AdFilters::rebuildAll();
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_filters');
    }
};
