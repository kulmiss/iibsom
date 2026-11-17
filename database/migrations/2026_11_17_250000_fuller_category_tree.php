<?php

use App\Models\Ad;
use App\Models\Category;
use App\Support\Ads\AdCounters;
use App\Support\Catalog;
use Database\Seeders\CategorySeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Round 2 (PR 50): the fuller Somali category tree on sites that already exist.
 * - Adds the new sub categories and fields that are missing (nothing an admin set is changed).
 * - Renames 4 sub categories, but only when an admin did not change the name already.
 * - Moves "Solar & Generators" to Electronics, unless an admin moved it.
 * - Moves DEMO bus and sheep ads to the new sub categories (real ads are never moved).
 * Slugs stay the same, so old links keep working.
 */
return new class extends Migration
{
    private const RENAMES = [
        'trucks-buses' => ['Trucks & Buses', 'Trucks', 'Gaari xamuul'],
        'vehicle-parts' => ['Vehicle Parts', 'Vehicle Parts & Accessories', 'Qalabka & Agabka gaadiidka'],
        'cattle' => ['Cattle', 'Cows', "Lo'"],
        'goats-sheep' => ['Goats & Sheep', 'Goats', 'Riyo'],
    ];

    public function up(): void
    {
        if (! Category::query()->exists()) {
            return; // a new site: the normal seeder makes the whole tree
        }

        foreach (self::RENAMES as $slug => [$old, $en, $so]) {
            $category = Category::where('slug', $slug)->first();
            $names = $category?->getAttribute('name');
            if (is_array($names) && ($names['en'] ?? null) === $old) {
                $category->forceFill(['name' => ['en' => $en, 'so' => $so]])->save();
            }
        }

        $solar = Category::where('slug', 'solar-generators')->first();
        $construction = Category::where('slug', 'construction')->value('id');
        $electronics = Category::where('slug', 'electronics')->value('id');
        if ($solar && $electronics && $solar->parent_id === $construction) {
            $solar->forceFill(['parent_id' => $electronics])->save();
        }

        (new CategorySeeder)->addMissing(); // only what is missing: the admin's order, icons and parents stay

        $moves = [
            ['trucks-buses', 'buses', '/(bus|coaster|minibus)/i'],
            ['goats-sheep', 'sheep', '/sheep/i'],
        ];
        foreach ($moves as [$from, $to, $pattern]) {
            $fromId = Category::where('slug', $from)->value('id');
            $toId = Category::where('slug', $to)->value('id');
            if ($fromId && $toId) {
                Ad::withTrashed()->where('is_demo', true)->where('category_id', $fromId)->get()
                    ->filter(fn (Ad $ad) => preg_match($pattern, $ad->title) === 1)
                    ->each(fn (Ad $ad) => Ad::withoutEvents(fn () => $ad->forceFill(['category_id' => $toId])->save()));
            }
        }

        AdCounters::refreshAll();
        app(Catalog::class)->flush();
    }

    public function down(): void
    {
        // Nothing to undo safely: new sub categories may already have ads.
    }
};
