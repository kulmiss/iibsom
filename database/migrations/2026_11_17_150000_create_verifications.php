<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Verified badge (Phase 2, switch "verified_badge").
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable()->after('phone_has_whatsapp');
        });

        Schema::create('verification_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('id_type', 20); // national_id | passport | driving_licence
            $table->string('file_path')->nullable(); // encrypted ID photo on the private disk; deleted after the decision
            $table->string('status', 10)->default('pending'); // pending | approved | rejected
            $table->string('reason', 300)->nullable(); // why it was rejected (the user sees it)
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_requests');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('verified_at'));
    }
};
