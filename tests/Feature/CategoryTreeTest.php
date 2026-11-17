<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_main_category_has_live_sub_categories_with_somali_names(): void
    {
        $this->seed();
        $mains = Category::whereNull('parent_id')->with('children')->get();
        $this->assertGreaterThanOrEqual(15, $mains->count());
        foreach ($mains as $main) {
            $this->assertNotEmpty($main->children, "{$main->label} has no sub categories");
            foreach ([$main, ...$main->children] as $c) {
                $this->assertNotEmpty($c->getAttribute('name')['so'] ?? null, "No Somali name for {$c->label}");
                $this->assertFileExists(resource_path("svg/icons/{$c->icon}.svg"));
            }
        }
        foreach (['buses', 'boats', 'rooms', 'sheep', 'traditional-wear', 'perfume', 'solar-generators'] as $slug) {
            $this->assertTrue(Category::where('slug', $slug)->whereNotNull('parent_id')->exists(), $slug);
        }
        $this->assertSame('electronics', Category::where('slug', 'solar-generators')->firstOrFail()->parent?->slug);
        $parts = Category::where('slug', 'vehicle-parts')->firstOrFail()->allFields()->firstWhere('key', 'part_type');
        $this->assertNotNull($parts);
        $this->assertArrayHasKey('wheels-tyres', $parts->optionList());
    }

    /** Sites made before PR 50: names/places change only where an admin did not change them; demo bus/sheep ads move. */
    public function test_upgrade_of_an_existing_site(): void
    {
        $this->seed();
        // Put the site back the way it was before PR 50.
        Category::whereIn('slug', ['buses', 'sheep', 'boats', 'rooms'])->delete();
        Category::where('slug', 'trucks-buses')->firstOrFail()->forceFill(['name' => ['en' => 'Trucks & Buses', 'so' => 'Gaari xamuul & Bas']])->save();
        Category::where('slug', 'goats-sheep')->firstOrFail()->forceFill(['name' => ['en' => 'Our Goats', 'so' => 'Riyahayaga']])->save(); // admin's own name
        $solar = Category::where('slug', 'solar-generators')->firstOrFail();
        $solar->forceFill(['parent_id' => Category::where('slug', 'construction')->value('id')])->save();

        $make = fn (string $slug, string $title, bool $demo) => Ad::factory()->live()->create([
            'user_id' => User::factory()->completed()->create()->id, 'title' => $title, 'is_demo' => $demo,
            'category_id' => Category::where('slug', $slug)->value('id'), 'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);
        $demoBus = $make('trucks-buses', 'Toyota Coaster bus 30 seats', true);
        $realBus = $make('trucks-buses', 'My Coaster bus for sale', false);
        $demoSheep = $make('goats-sheep', 'Fat sheep for weddings', true);
        $demoTruck = $make('trucks-buses', 'Isuzu NPR truck 2009', true);

        (require database_path('migrations/2026_11_17_250000_fuller_category_tree.php'))->up();

        $this->assertSame('Trucks', Category::where('slug', 'trucks-buses')->firstOrFail()->getAttribute('name')['en']);
        $this->assertSame('Our Goats', Category::where('slug', 'goats-sheep')->firstOrFail()->getAttribute('name')['en'], 'Admin names are kept.');
        $this->assertSame('electronics', $solar->fresh()?->parent?->slug);
        $this->assertSame('buses', $demoBus->fresh()?->category->slug);
        $this->assertSame('sheep', $demoSheep->fresh()?->category->slug);
        $this->assertSame('trucks-buses', $realBus->fresh()?->category->slug, 'Real ads are never moved.');
        $this->assertSame('trucks-buses', $demoTruck->fresh()?->category->slug);
        // Counters recounted (demo ads are not counted while demo data is hidden): only the real bus.
        $this->assertSame(1, Category::where('slug', 'trucks-buses')->firstOrFail()->ads_count);
    }
}
