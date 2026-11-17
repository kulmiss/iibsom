<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Search\AdSearch;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Real MySQL FULLTEXT (needs committed rows, so no transaction here). */
class FullTextSearchTest extends TestCase
{
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        // Rows here are really saved (no transaction): leave an empty database for the next tests.
        $this->artisan('migrate:fresh');
        parent::tearDown();
    }

    public function test_fulltext_finds_words_and_prefixes(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('FULLTEXT needs MySQL/MariaDB.');
        }
        $this->seed();
        config(['iibsom.search_fulltext' => true]);
        $cars = Category::where('slug', 'cars')->value('id');
        Ad::factory()->live()->create(['category_id' => $cars, 'title' => 'Toyota Land Cruiser V8', 'description' => 'Diesel, very clean.']);
        Ad::factory()->live()->create(['category_id' => $cars, 'title' => 'Suzuki Swift small', 'description' => 'Petrol, good on fuel.']);

        $search = app(AdSearch::class);
        $this->assertSame(['Toyota Land Cruiser V8'], $search->search(['q' => 'cruiser'])->pluck('title')->all());
        $this->assertSame(['Toyota Land Cruiser V8'], $search->search(['q' => 'toyo diesel'])->pluck('title')->all());
        $this->assertSame([], $search->search(['q' => 'toyota petrol'])->pluck('title')->all());
    }
}
