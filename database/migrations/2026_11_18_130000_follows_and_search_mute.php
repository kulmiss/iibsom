<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PR 67: follow a seller (switch "sellers.follow") and mute a saved search (switch "saved_searches.manage").
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table) {
            $table->foreignId('follower_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['follower_id', 'seller_id']);
            $table->index(['seller_id', 'follower_id']);
        });

        Schema::table('saved_searches', function (Blueprint $table) {
            $table->boolean('muted')->default(false); // no alerts (email or bell) while true
        });
    }

    public function down(): void
    {
        Schema::table('saved_searches', fn (Blueprint $table) => $table->dropColumn('muted'));
        Schema::dropIfExists('follows');
    }
};
