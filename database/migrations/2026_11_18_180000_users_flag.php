<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// PR 73: a person whose chat message was blocked by the drug check is marked for staff review (never banned
// automatically). Only the word, how many times and when are kept; the blocked message itself is not saved.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('flag', 20)->nullable();
            $table->string('flag_word', 60)->nullable();
            $table->unsignedSmallInteger('flag_count')->default(0);
            $table->timestamp('flagged_at')->nullable();
            $table->index('flag');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['flag']);
            $table->dropColumn(['flag', 'flag_word', 'flag_count', 'flagged_at']);
        });
    }
};
