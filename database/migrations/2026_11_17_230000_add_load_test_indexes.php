<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Load test (100k ads): "newest live ads" (home page, API) took 0.25 s without the first index, 1 ms with it.
// created_at: the admin dashboard counts (today / 7 / 30 days) and the admin ads list (newest first).
// users (role, is_demo, created_at): the admin users list took 0.5 s at 10k users (counted every user's ads before sorting).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->index(['status', 'published_at']);
            $table->index('created_at');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'is_demo', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropIndex(['status', 'published_at']);
            $table->dropIndex(['created_at']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'is_demo', 'created_at']);
        });
    }
};
