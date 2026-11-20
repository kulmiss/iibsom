<?php

use App\Support\Features\FeatureManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// PR 100: owner — no text under the title on category pages. The "seo.intro" switch is now OFF by default;
// a saved ON is removed so it follows the new default (the owner can switch it ON again in Feature switches).
return new class extends Migration
{
    public function up(): void
    {
        DB::table('features')->where('key', 'seo.intro')->delete();
        app(FeatureManager::class)->flush();
    }

    public function down(): void
    {
        // Nothing to undo: an old default comes back with the old code.
    }
};
