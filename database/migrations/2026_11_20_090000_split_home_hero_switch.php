<?php

use App\Support\Features\FeatureManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// PR 86: "home.hero_plus" became three switches. A site that had it OFF keeps those three parts hidden.
return new class extends Migration
{
    private const NEW = ['home.promise', 'home.popular_searches', 'home.post_button'];

    public function up(): void
    {
        $old = DB::table('features')->where('key', 'home.hero_plus')->first();
        if ($old !== null) {
            foreach (self::NEW as $key) {
                if (! DB::table('features')->where('key', $key)->exists()) {
                    DB::table('features')->insert(['key' => $key, 'enabled' => $old->enabled, 'updated_by' => $old->updated_by ?? null, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            DB::table('features')->where('key', 'home.hero_plus')->delete();
        }
        app(FeatureManager::class)->flush();
    }

    public function down(): void
    {
        // Back to one switch: ON unless all three were OFF.
        $rows = DB::table('features')->whereIn('key', self::NEW)->pluck('enabled', 'key');
        if ($rows->isNotEmpty() && ! DB::table('features')->where('key', 'home.hero_plus')->exists()) {
            $on = count($rows) < 3 || $rows->contains(fn ($v) => (bool) $v);
            DB::table('features')->insert(['key' => 'home.hero_plus', 'enabled' => $on, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('features')->whereIn('key', self::NEW)->delete();
        app(FeatureManager::class)->flush();
    }
};
