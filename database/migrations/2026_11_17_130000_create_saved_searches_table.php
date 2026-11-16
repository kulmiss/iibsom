<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Saved searches + daily email alerts (Phase 2, switch "saved_searches").
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 150);
            $table->json('criteria');
            $table->char('hash', 64);
            // Ads published after this time are "new" (an ad approved late still counts: it is published when approved).
            $table->timestamp('checked_until');
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
