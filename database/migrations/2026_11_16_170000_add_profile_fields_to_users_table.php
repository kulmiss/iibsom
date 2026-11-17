<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Customers sign in with Google: no password.
            $table->string('password')->nullable()->change();

            $table->string('google_id', 64)->nullable()->unique()->after('email');
            $table->string('avatar_url', 500)->nullable()->after('google_id');

            // Shown to buyers so they can call / WhatsApp the seller. Stored as +252XXXXXXXXX.
            $table->string('phone', 20)->nullable()->index()->after('avatar_url');
            $table->boolean('phone_has_whatsapp')->default(true)->after('phone');
            $table->foreignId('city_id')->nullable()->after('phone_has_whatsapp')->constrained()->nullOnDelete();

            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamp('profile_completed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('banned_at')->nullable();
            $table->string('ban_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
            $table->dropColumn(['google_id', 'avatar_url', 'phone', 'phone_has_whatsapp', 'terms_accepted_at', 'profile_completed_at', 'last_login_at', 'banned_at', 'ban_reason']);
        });
    }
};
