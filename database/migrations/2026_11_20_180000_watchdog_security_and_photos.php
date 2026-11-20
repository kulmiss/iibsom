<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Watchdog part 2 (PR 95):
 * - staff_devices: browsers staff signed in from (a random id in a cookie, kept only as a hash), to tell
 *   the owner about a sign-in from a new browser. No IP is kept.
 * - ad_photos.phash: a short fingerprint of each photo, to find many ads with the same photo (copy spam).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('device_hash', 64);
            $table->string('label', 60);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');

            $table->unique(['user_id', 'device_hash']);
        });

        Schema::table('ad_photos', function (Blueprint $table) {
            $table->char('phash', 16)->nullable()->after('height')->index();
        });
    }

    public function down(): void
    {
        Schema::table('ad_photos', function (Blueprint $table) {
            $table->dropIndex(['phash']);
            $table->dropColumn('phash');
        });
        Schema::dropIfExists('staff_devices');
    }
};
