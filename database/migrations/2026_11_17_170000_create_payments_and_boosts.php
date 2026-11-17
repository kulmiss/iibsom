<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Paid boosts (Phase 2, switch "boosts"): receipt payments, a double-entry ledger, boosts.
// Money is always whole cents (unsigned integers), never decimals.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->unsignedTinyInteger('boost_level')->default(0)->after('is_demo'); // 0 none, 1 Top, 2 VIP, 3 Diamond
            $table->timestamp('boosted_until')->nullable()->after('boost_level');
            $table->index(['status', 'boost_level', 'published_at']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete(); // money records are never deleted with a user
            $table->foreignId('ad_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 20)->default('boost');
            $table->string('package', 20);
            $table->string('method', 20); // evc_plus | zaad | sahal | edahab
            $table->unsignedBigInteger('amount_cents');
            $table->char('currency', 3)->default('USD');
            $table->string('reference', 40); // the mobile-money transaction ID the payer typed
            $table->string('receipt_path')->nullable(); // encrypted screenshot, private disk
            $table->string('status', 10)->default('pending'); // pending | approved | rejected
            $table->string('reason', 300)->nullable();
            $table->boolean('is_test')->default(true);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['method', 'reference']); // one transaction ID can pay only once
            $table->index(['status', 'created_at']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_id');
            $table->string('account', 40); // e.g. cash:evc_plus, revenue:boosts
            $table->unsignedBigInteger('debit_cents')->default(0);
            $table->unsignedBigInteger('credit_cents')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->foreignId('payment_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_test')->default(true);
            $table->string('memo', 200)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('transaction_id');
            $table->index(['account', 'is_test']);
        });

        Schema::create('ad_boosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->unique()->constrained()->restrictOnDelete(); // one boost per payment
            $table->string('package', 20);
            $table->unsignedTinyInteger('level');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_boosts');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('payments');
        Schema::table('ads', function (Blueprint $table) {
            $table->dropIndex(['status', 'boost_level', 'published_at']);
            $table->dropColumn(['boost_level', 'boosted_until']);
        });
    }
};
