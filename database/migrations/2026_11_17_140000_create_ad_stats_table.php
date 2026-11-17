<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ad stats (Phase 2, switch "ad_stats"): counts per ad per day. No IP, no user, no cookie.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_stats', function (Blueprint $table) {
            $table->foreignId('ad_id')->constrained()->cascadeOnDelete();
            $table->date('day'); // Mogadishu date
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('phone')->default(0);   // "Show phone number" taps
            $table->unsignedInteger('whatsapp')->default(0);
            $table->unsignedInteger('call')->default(0);
            $table->primary(['ad_id', 'day']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_stats');
    }
};
