<?php

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Database\Migrations\Migration;

// Jiji round (PR 65): "Body type" on Cars and "RAM" on Mobile Phones for sites that already exist.
// Only adds what is missing; nothing an admin changed is touched.
return new class extends Migration
{
    public function up(): void
    {
        if (Category::query()->exists()) {
            (new CategorySeeder)->addMissing();
        }
    }

    public function down(): void
    {
        // Fields stay (ads may already use them); an admin can remove them in Categories.
    }
};
