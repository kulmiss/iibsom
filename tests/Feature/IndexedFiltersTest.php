<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Search\AdSearch;
use App\Support\Features\FeatureManager;
use App\Support\Search\AdFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** PR 90: detail filters (brand, year, mileage…) use an indexed table, with exactly the old results. */
class IndexedFiltersTest extends TestCase
{
    use RefreshDatabase;

    private int $cars;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->cars = (int) Category::where('slug', 'cars')->value('id');
    }

    /** @param array<string, mixed> $fields */
    private function car(array $fields): Ad
    {
        return Ad::factory()->live()->create(['category_id' => $this->cars, 'fields' => $fields]);
    }

    /** @param array<string, mixed> $fields @return list<int> */
    private function ids(array $fields, bool $indexed): array
    {
        app(FeatureManager::class)->set('search.indexed_filters', $indexed);
        $page = app(AdSearch::class)->search(['category_ids' => [$this->cars], 'fields' => $fields], 100, 1);
        $ids = collect($page->items())->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        return $ids;
    }

    public function test_same_results_as_before_for_every_kind_of_filter(): void
    {
        $brands = ['toyota', 'nissan', 'honda', 'suzuki'];
        mt_srand(42);
        foreach (range(1, 40) as $i) {
            $fields = ['brand' => $brands[$i % 4], 'year' => 1995 + mt_rand(0, 30), 'mileage' => mt_rand(0, 300000), 'fuel' => $i % 3 ? 'petrol' : 'diesel'];
            if ($i % 5 === 0) {
                $fields['year'] = (string) $fields['year']; // older ads may keep numbers as text
            }
            if ($i % 7 === 0) {
                unset($fields['year']); // some ads leave a detail empty
            }
            $this->car($fields);
        }

        $cases = [
            ['brand' => 'toyota'],
            ['brand' => 'toyota', 'fuel' => 'diesel'],
            ['brand' => 'nothing-like-this'],
            ['year' => ['min' => '2010']],
            ['year' => ['max' => '2005']],
            ['year' => ['min' => '2000', 'max' => '2015'], 'brand' => 'honda'],
            ['mileage' => ['min' => '50000', 'max' => '150000']],
            ['year' => ['min' => '', 'max' => '']],
            ['brand' => ''],
        ];
        foreach ($cases as $case) {
            $old = $this->ids($case, false);
            $new = $this->ids($case, true);
            $this->assertSame($old, $new, json_encode($case));
        }
        $this->assertNotEmpty($this->ids(['brand' => 'toyota'], true));
    }

    public function test_yes_no_details_match_like_before(): void
    {
        $yes = $this->car(['brand' => 'toyota', 'warranty' => true]);
        $this->car(['brand' => 'toyota', 'warranty' => false]);
        $this->car(['brand' => 'toyota']);

        $this->assertSame([$yes->id], $this->ids(['warranty' => '1'], true));
        $this->assertSame($this->ids(['warranty' => '1'], false), $this->ids(['warranty' => '1'], true));
    }

    public function test_the_table_follows_every_change(): void
    {
        $ad = $this->car(['brand' => 'toyota', 'year' => 2012]);
        $this->assertSame(2, DB::table('ad_filters')->where('ad_id', $ad->id)->count());

        $ad->update(['fields' => ['brand' => 'nissan']]);
        $this->assertSame([['brand', 'nissan']], DB::table('ad_filters')->where('ad_id', $ad->id)->get()->map(fn ($r) => [$r->key, $r->text_value])->all());

        $ad->update(['title' => 'Only the title changed']); // details untouched: nothing rewritten
        $this->assertSame(1, DB::table('ad_filters')->where('ad_id', $ad->id)->count());

        $ad->forceDelete();
        $this->assertSame(0, DB::table('ad_filters')->where('ad_id', $ad->id)->count());
    }

    public function test_long_text_uses_the_old_way_and_still_matches(): void
    {
        $long = str_repeat('very long colour name ', 10); // over 120 characters
        $ad = $this->car(['brand' => 'toyota', 'colour' => $long]);
        $this->assertNull(DB::table('ad_filters')->where('ad_id', $ad->id)->where('key', 'colour')->value('text_value'));
        $this->assertSame([$ad->id], $this->ids(['colour' => $long], true));
    }

    public function test_rebuild_command_fills_rows_for_ads_saved_without_model_events(): void
    {
        $ad = $this->car(['brand' => 'toyota']);
        DB::table('ads')->where('id', $ad->id)->update(['fields' => json_encode(['brand' => 'honda', 'year' => 2001])]);
        $this->assertSame([], $this->ids(['brand' => 'honda'], true));

        Artisan::call('iibsom:filters:rebuild');
        $this->assertSame([$ad->id], $this->ids(['brand' => 'honda'], true));
        $this->assertSame([$ad->id], $this->ids(['year' => ['min' => '2000', 'max' => '2002']], true));
    }

    public function test_rows_ignore_odd_keys_and_values(): void
    {
        $rows = AdFilters::rows(7, ['Bad Key' => 'x', 'ok' => ['nested'], 'n' => '  2012 ', 'f' => 1.9, 'empty' => null]);
        $this->assertSame([
            ['ad_id' => 7, 'key' => 'n', 'text_value' => '  2012 ', 'num_value' => 2012],
            ['ad_id' => 7, 'key' => 'f', 'text_value' => '1.9', 'num_value' => 1],
        ], $rows);
        $this->assertSame([], AdFilters::rows(7, 'not json'));
    }

    public function test_the_query_uses_the_table(): void
    {
        $this->car(['brand' => 'toyota']);
        app(FeatureManager::class)->set('search.indexed_filters', true);
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(AdSearch::class)->search(['category_ids' => [$this->cars], 'fields' => ['brand' => 'toyota']], 20, 1);
        $sql = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();
        $this->assertStringContainsString('`ad_filters`', $sql);
        $this->assertStringNotContainsString('json_extract(`fields`', $sql);
    }
}
