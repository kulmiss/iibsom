<?php

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Advanced filters (PR 52): a view total for "Most viewed", indexes for price and views inside a category,
// and the new "Colour" field for cars on sites that already exist.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->unsignedInteger('views')->default(0)->after('boosted_until');
            $table->index(['status', 'category_id', 'views']);
            $table->index(['status', 'category_id', 'price']);
        });

        if (Category::query()->exists()) {
            (new CategorySeeder)->addMissing(); // adds the Colour field; changes nothing an admin set
        }
    }

    public function down(): void
    {
        Schema::table('ads', function (Blueprint $table) {
            $table->dropIndex(['status', 'category_id', 'views']);
            $table->dropIndex(['status', 'category_id', 'price']);
            $table->dropColumn('views');
        });
    }
};
