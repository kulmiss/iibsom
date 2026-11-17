<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Seller ratings (switch "seller_ratings"): only buyers who chatted with the seller, one per conversation.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('conversation_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('ad_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->string('comment', 500)->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->foreignId('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('hidden_reason', 200)->nullable();
            $table->timestamps();
            $table->index(['seller_id', 'hidden_at', 'created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Kept up to date on every change, so ad pages need no extra query.
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('rating_sum')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['rating_count', 'rating_sum']));
        Schema::dropIfExists('seller_reviews');
    }
};
