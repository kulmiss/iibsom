<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Scam number warning list (PR 109). Staff only, never automatic. No names: only the number and why.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scam_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique(); // +252… (one row per number; removed ones keep the history)
            $table->string('reason', 300); // short, may be shown to the number's owner in an appeal answer
            $table->text('proof')->nullable(); // staff-only note: what proves it (report numbers, messages)
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('removed_at')->nullable(); // null = on the list
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('removed_reason', 300)->nullable();
            $table->timestamps();
            $table->index('removed_at');
        });

        Schema::create('scam_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scam_number_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->string('contact', 120)->nullable(); // how to answer; removed 30 days after the decision
            $table->string('status', 10)->default('waiting'); // waiting | accepted | refused
            $table->string('decision_note', 300)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scam_appeals');
        Schema::dropIfExists('scam_numbers');
    }
};
