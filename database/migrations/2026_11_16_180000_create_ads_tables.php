<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title', 100);
            $table->string('slug', 120);
            $table->text('description');
            $table->unsignedBigInteger('price')->nullable(); // whole US dollars
            $table->boolean('price_negotiable')->default(false);
            $table->string('condition', 10)->nullable(); // new | used
            $table->json('fields')->nullable();           // custom field values {"brand":"toyota","year":2015}

            // pending → live → sold/expired ; or pending → rejected
            $table->string('status', 12)->default('pending');
            $table->string('rejection_reason', 300)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->string('content_hash', 64)->nullable()->index(); // duplicate check
            $table->timestamps();
            $table->softDeletes();

            // Listing queries: live ads by category / city, newest first; price sorting.
            $table->index(['status', 'category_id', 'published_at']);
            $table->index(['status', 'city_id', 'published_at']);
            $table->index(['status', 'price']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE ads ADD FULLTEXT ads_search (title, description)');
        }

        Schema::create('ad_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->nullable()->constrained()->cascadeOnDelete(); // null while the ad is being written
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('upload_path')->nullable(); // private temporary original
            $table->string('disk', 20)->nullable();
            $table->json('variants')->nullable();      // {"thumb":{"webp":"...","avif":"..."},"medium":{...},"large":{...}}
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedTinyInteger('sort')->default(0);
            $table->string('status', 12)->default('processing'); // processing | ready | failed
            $table->timestamps();

            $table->index(['ad_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_photos');
        Schema::dropIfExists('ads');
    }
};
