<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Watchdog (PR 94): one row per problem "episode". Open while fixed_at is empty;
 * a problem that comes back after it was fixed gets a new row (that is the history).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watchdog_problems', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120);
            $table->string('area', 20);
            $table->string('level', 10);
            $table->string('title', 200);
            $table->text('message');
            $table->json('steps')->nullable();
            $table->json('data')->nullable();
            $table->string('subject_type', 20)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedInteger('count')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('alerted_at')->nullable();
            $table->timestamp('last_alert_at')->nullable();
            $table->boolean('pending_alert')->default(false);
            $table->timestamp('muted_until')->nullable();
            $table->foreignId('muted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fixed_at')->nullable();
            $table->foreignId('fixed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('fixed_how', 10)->nullable(); // auto | staff | off | event
            $table->timestamps();

            // Only one OPEN row per key, even when two runs overlap.
            $table->string('open_key', 120)->nullable()->storedAs('if(`fixed_at` is null, `key`, null)')->unique();
            $table->index(['area', 'fixed_at']);
            $table->index('first_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchdog_problems');
    }
};
