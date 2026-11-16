<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Region → City → Area (e.g. Banadir → Mogadishu → Hodan).
        // "enabled" is the on/off switch for each one (admin can change it).
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->json('name');
            $table->string('slug', 80)->unique();
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->string('slug', 80)->unique();
            $table->boolean('enabled')->default(false);
            $table->boolean('popular')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->unsignedInteger('ads_count')->default(0);
            $table->timestamps();

            $table->index(['enabled', 'sort']);
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->string('slug', 80);
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['city_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('regions');
    }
};
