<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Banner ads (Phase 2, switch "banner_ads"): banners in paid slots, aimed at a category and/or
// city, with click counts. Existing banners stay home-slider banners.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('placement', 10)->default('slider')->after('id'); // slider | home | list | ad
            $table->string('advertiser', 100)->nullable()->after('placement');
            $table->foreignId('category_id')->nullable()->after('advertiser')->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
            $table->unsignedBigInteger('clicks')->default(0);
            $table->index(['placement', 'enabled']);
        });

        // Clicks per banner per day: only a number, nothing about who clicked.
        Schema::create('banner_clicks', function (Blueprint $table) {
            $table->foreignId('banner_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('clicks')->default(0);
            $table->primary(['banner_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banner_clicks');
        Schema::table('banners', function (Blueprint $table) {
            $table->dropIndex(['placement', 'enabled']);
            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('city_id');
            $table->dropColumn(['placement', 'advertiser', 'clicks']);
        });
    }
};
