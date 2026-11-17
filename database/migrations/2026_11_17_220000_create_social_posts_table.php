<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Auto-post (switch "social.autopost"): one row per ad per network, so an ad is never shared twice.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained()->cascadeOnDelete();
            $table->string('network', 20);
            $table->string('status', 10)->default('queued'); // queued, posted, failed, skipped
            $table->string('remote_id', 100)->nullable();
            $table->string('error', 300)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['ad_id', 'network']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
