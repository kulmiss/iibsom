<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Dates are kept in UTC but people read them in Somalia (UTC+3): late in the
 * evening (UTC) it is already the next day in Mogadishu. Bug hunt D21.
 */
class MogadishuDatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_my_ads_end_date_is_the_day_in_mogadishu(): void
    {
        $seller = User::factory()->completed()->create(['created_at' => Carbon::parse('2026-01-31 22:30', 'UTC')]);
        Ad::factory()->live()->create([
            'user_id' => $seller->id,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
            'expires_at' => Carbon::parse('2026-12-01 22:30', 'UTC'), // 2 Dec 01:30 in Mogadishu
        ]);

        $this->actingAs($seller)->get('/account/ads')->assertOk()->assertSee('2 Dec 2026')->assertDontSee('1 Dec 2026');
        // Joined 31 Jan 22:30 UTC = 1 Feb in Mogadishu.
        $this->actingAs($seller)->get('/account')->assertOk()->assertSee('Feb 2026')->assertDontSee('Jan 2026');
    }

    public function test_page_updated_date_is_the_day_in_mogadishu(): void
    {
        $page = Page::where('slug', 'about')->firstOrFail();
        $page->timestamps = false;
        $page->forceFill(['updated_at' => Carbon::parse('2026-03-09 21:15', 'UTC')])->save();

        $this->get('/about')->assertOk()->assertSee('10 Mar 2026');
    }
}
