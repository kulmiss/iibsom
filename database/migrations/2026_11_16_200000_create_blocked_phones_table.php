<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Phone numbers that may not be used on iibsom (scammers).
        Schema::create('blocked_phones', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique(); // +252XXXXXXXXX
            $table->string('reason', 200)->nullable();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_phones');
    }
};
