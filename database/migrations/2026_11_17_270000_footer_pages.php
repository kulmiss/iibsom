<?php

use App\Models\Page;
use Database\Seeders\PageSeeder;
use Illuminate\Database\Migrations\Migration;

// The big footer (PR 53): "Cookie info" and "Copyright and reporting a problem" pages on sites that already
// exist. The page seeder only adds what is missing, so pages edited in the admin stay as they are.
return new class extends Migration
{
    public function up(): void
    {
        if (Page::query()->exists()) {
            (new PageSeeder)->run();
        }
    }

    public function down(): void
    {
        Page::whereIn('slug', ['cookies', 'copyright'])->delete();
    }
};
