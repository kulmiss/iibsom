<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Two levels: main category (Vehicles) → sub category (Cars).
        // New categories are added as data, no code needed.
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->cascadeOnDelete();
            $table->json('name');
            $table->string('slug', 80)->unique();
            $table->string('icon', 40)->default('tag');
            $table->boolean('enabled')->default(true);
            $table->boolean('popular')->default(false);
            $table->boolean('has_condition')->default(true); // new/used makes no sense for jobs, services
            $table->unsignedSmallInteger('sort')->default(0);
            $table->unsignedInteger('ads_count')->default(0); // kept up to date when ads change
            $table->timestamps();

            $table->index(['parent_id', 'enabled', 'sort']);
        });

        // Custom fields per category (cars: brand, year, km...). Children inherit the parent's fields.
        Schema::create('category_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('key', 50);
            $table->string('type', 20); // text | number | select | boolean
            $table->json('label');
            $table->json('options')->nullable(); // for select: [{"value":"toyota","label":{"en":"Toyota"}}]
            $table->string('unit', 20)->nullable(); // km, m², GB...
            $table->boolean('required')->default(false);
            $table->boolean('filterable')->default(true);
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_fields');
        Schema::dropIfExists('categories');
    }
};
