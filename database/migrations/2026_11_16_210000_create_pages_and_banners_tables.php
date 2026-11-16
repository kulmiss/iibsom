<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // About, Terms, Privacy, Safety tips... edited in the admin (Markdown, per language).
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->json('title');
            $table->json('body');
            $table->boolean('enabled')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Home page banners.
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->string('link_url', 300)->nullable();
            $table->string('disk', 20)->nullable();
            $table->json('image')->nullable(); // {"wide":"...webp","small":"...webp"}
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
        Schema::dropIfExists('pages');
    }
};
