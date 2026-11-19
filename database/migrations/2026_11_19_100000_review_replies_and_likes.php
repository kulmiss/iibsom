<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Seller reviews (PR 85): the seller's one public answer, and "Helpful" likes from signed-in people.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_reviews', function (Blueprint $table) {
            $table->string('reply', 500)->nullable()->after('comment');
            $table->timestamp('replied_at')->nullable()->after('reply');
            // Kept up to date on every like, so the list needs no count query.
            $table->unsignedInteger('likes_count')->default(0)->after('replied_at');
        });

        Schema::create('review_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('seller_reviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['review_id', 'user_id']); // one like per person per review
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_likes');
        Schema::table('seller_reviews', fn (Blueprint $table) => $table->dropColumn(['reply', 'replied_at', 'likes_count']));
    }
};
