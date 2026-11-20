<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\CategoryField;
use App\Support\Routing\ReservedSlugs;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** PR 93: the fuller category tree (ours kept, ideas from Jiji added) on new and existing sites. */
class MoreCategoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_full_tree_is_complete_and_clean(): void
    {
        $this->assertSame(17, Category::whereNull('parent_id')->count());
        $this->assertSame(141, Category::whereNotNull('parent_id')->count());

        $somali = require database_path('seeders/data/somali.php');
        foreach (Category::all() as $c) {
            $this->assertFileExists(resource_path("svg/icons/{$c->icon}.svg"), $c->slug);
            $this->assertNotEmpty($c->name['so'] ?? null, "Somali name for {$c->slug}");
            $this->assertFalse(ReservedSlugs::has($c->slug), "{$c->slug} is a site address");
            $this->assertTrue($c->enabled);
        }
        // Never a category for these (drugs check, decency).
        foreach (['smoking', 'sexual'] as $word) {
            $this->assertFalse(Category::where('slug', 'like', "%{$word}%")->exists(), $word);
        }
        // Each new main category has its sub categories.
        $this->assertSame(9, Category::where('parent_id', Category::where('slug', 'commercial-equipment')->value('id'))->count());
        $this->assertSame(4, Category::where('parent_id', Category::where('slug', 'business')->value('id'))->count());
        $this->assertFalse((bool) Category::where('slug', 'business')->value('has_condition'));
    }

    public function test_jobs_get_a_field_of_work_filter(): void
    {
        foreach (['job-vacancies', 'looking-for-work'] as $slug) {
            $field = CategoryField::where('category_id', Category::where('slug', $slug)->value('id'))->where('key', 'job_field')->firstOrFail();
            $this->assertSame('select', $field->type);
            $this->assertTrue($field->filterable);
            $this->assertContains('aid-development-ngo', array_column($field->options, 'value'));
            $this->assertSame('Qaybta shaqada', $field->label['so']);
        }
        $this->get('/job-vacancies')->assertOk()->assertSee('Field of work');
    }

    public function test_an_existing_site_gets_only_what_is_missing(): void
    {
        // An existing site: without the new ones, and with the owner's own changes.
        $newSlugs = ['commercial-equipment', 'business', 'heavy-machinery', 'smart-watches', 'dogs', 'water-tanks'];
        foreach (Category::whereIn('slug', $newSlugs)->get() as $c) {
            Category::where('parent_id', $c->id)->delete();
            $c->delete();
        }
        CategoryField::where('key', 'job_field')->delete();
        $cars = Category::where('slug', 'cars')->firstOrFail();
        $cars->forceFill(['name' => ['en' => 'Cars for sale', 'so' => 'Gaari iib ah'], 'sort' => 99, 'enabled' => false, 'icon' => 'car-front'])->save();
        $ad = Ad::factory()->live()->create(['category_id' => Category::where('slug', 'cats')->value('id')]);

        (require database_path('migrations/2026_11_20_150000_more_categories.php'))->up();

        foreach ($newSlugs as $slug) {
            $this->assertTrue(Category::where('slug', $slug)->exists(), $slug);
        }
        $this->assertSame(158, Category::count());
        $this->assertSame(2, CategoryField::where('key', 'job_field')->count());
        $cars->refresh();
        $this->assertSame(['en' => 'Cars for sale', 'so' => 'Gaari iib ah'], $cars->name, 'admin name kept');
        $this->assertSame([99, false, 'car-front'], [$cars->sort, $cars->enabled, $cars->icon]);
        $this->assertSame(Category::where('slug', 'cats')->value('id'), $ad->fresh()->category_id, 'ads never move');
        // New main categories go after the existing ones.
        $this->assertGreaterThan(Category::where('slug', 'other')->value('sort'), Category::where('slug', 'business')->value('sort'));

        // Running it again changes nothing.
        $before = Category::orderBy('id')->get(['id', 'slug', 'sort', 'parent_id'])->toArray();
        (require database_path('migrations/2026_11_20_150000_more_categories.php'))->up();
        $this->assertSame($before, Category::orderBy('id')->get(['id', 'slug', 'sort', 'parent_id'])->toArray());
    }

    public function test_full_seeder_twice_keeps_one_of_each(): void
    {
        $this->seed(CategorySeeder::class);
        $this->assertSame(158, Category::count());
        $this->assertSame(Category::count(), Category::distinct()->count('slug'));
    }
}
