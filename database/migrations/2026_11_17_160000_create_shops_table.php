<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Business shop pages (Phase 2, switch "shops"): one shop per seller, live while an admin has it active.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('slug', 40)->unique();
            $table->string('description', 1000)->nullable();
            $table->string('hours', 100)->nullable(); // opening hours, free text
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('logo_disk', 20)->nullable();
            $table->json('logo')->nullable(); // {"large": path, "small": path}
            $table->date('active_until')->nullable(); // set by an admin (monthly plan); null = not active
            $table->timestamps();
            $table->index('active_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
