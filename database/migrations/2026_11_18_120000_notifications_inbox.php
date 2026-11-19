<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Notifications (PR 66, switch "notifications"): the bell inbox, each person's choices,
// and the price an ad had when it was saved (for "price dropped" on Saved ads).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbox_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->foreignId('ad_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('data')->nullable(); // words for the text (title, label, price); shown in the reader's language
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'id']);
            $table->index(['user_id', 'read_at']);
            $table->index(['kind', 'ad_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('notify_settings')->nullable(); // null = everything ON (see App\Support\Notify\Inbox::DEFAULTS)
        });

        Schema::table('favourites', function (Blueprint $table) {
            $table->unsignedInteger('price_when_saved')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('favourites', fn (Blueprint $table) => $table->dropColumn('price_when_saved'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('notify_settings'));
        Schema::dropIfExists('inbox_items');
    }
};
