<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // customer | super_admin | moderator | support
            $table->string('role', 20)->default('customer')->index()->after('email');
            // Staff 2FA (encrypted at rest).
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->boolean('is_demo')->default(false)->index();
        });

        // Who did what in the admin (approve ad, ban user, change switch...).
        Schema::create('admin_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60)->index();             // e.g. ad.approved
            $table->string('subject_type', 40)->nullable();     // e.g. ad
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('summary', 300);
            $table->json('details')->nullable();
            $table->string('ip', 45)->nullable();               // staff only (signed in)
            $table->boolean('is_demo')->default(false);
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['subject_type', 'subject_id']);
        });

        // "Report this ad" (the button comes with the ad page; table now for the dashboard + admin queue).
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 20);      // scam | wrong_category | sold | offensive | other
            $table->string('details', 500)->nullable();
            $table->string('status', 12)->default('waiting'); // waiting | handled | rejected
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->string('staff_note', 300)->nullable();
            $table->boolean('is_demo')->default(false);
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->unique(['ad_id', 'user_id']); // one report per person per ad
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Schema::dropIfExists('admin_activities');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'is_demo']);
        });
    }
};
