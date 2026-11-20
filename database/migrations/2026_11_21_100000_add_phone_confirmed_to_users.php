<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// "Phone confirmed" tick (PR 108): a staff member confirmed the number by a call or WhatsApp. No SMS.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('phone_confirmed_at')->nullable()->after('phone_has_whatsapp');
            $table->foreignId('phone_confirmed_by')->nullable()->after('phone_confirmed_at')->constrained('users')->nullOnDelete();
            // Short code the seller sends by WhatsApp so staff can match the message to the account.
            $table->string('phone_confirm_code', 12)->nullable()->unique()->after('phone_confirmed_by');
            $table->timestamp('phone_confirm_code_at')->nullable()->after('phone_confirm_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('phone_confirmed_by');
            $table->dropUnique(['phone_confirm_code']);
            $table->dropColumn(['phone_confirmed_at', 'phone_confirm_code', 'phone_confirm_code_at']);
        });
    }
};
