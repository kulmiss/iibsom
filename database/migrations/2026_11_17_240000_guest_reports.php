<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Guest reports (switch "ads.guest_reports"): no user, optional contact, duplicates merged. No IP is stored.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change(); // null = a guest (not signed in)
            $table->string('contact', 120)->nullable()->after('details'); // phone or email, only if the guest wants an answer
            $table->unsignedInteger('times')->default(1)->after('contact'); // the same guest report sent again = +1, not a new row
            $table->char('fingerprint', 64)->nullable()->after('times'); // hash of ad + reason + note, to find duplicates
            $table->index(['ad_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex(['ad_id', 'fingerprint']);
            $table->dropColumn(['contact', 'times', 'fingerprint']);
        });
    }
};
