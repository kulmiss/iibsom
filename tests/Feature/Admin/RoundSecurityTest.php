<?php

namespace Tests\Feature\Admin;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\InboxItem;
use App\Models\User;
use App\Support\Features\FeatureManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/** Security check of the Jiji round (PR 70): races, other people's things, unsafe input. */
class RoundSecurityTest extends AdminTestCase
{
    private User $seller;

    private User $buyer;

    private Ad $ad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->completed()->create();
        $this->buyer = User::factory()->completed()->create();
        $this->ad = Ad::factory()->live()->create([
            'user_id' => $this->seller->id, 'published_at' => now()->subDays(10),
            'category_id' => Category::where('slug', 'cars')->value('id'), 'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);
    }

    /** Pretend another request saves the same row right after this one checked (two taps at the same moment). */
    private function racer(string $table, string $check, array $row): void
    {
        $done = false;
        Event::listen(QueryExecuted::class, function (QueryExecuted $q) use (&$done, $table, $check, $row) {
            if (! $done && str_contains($q->sql, $check) && str_contains($q->sql, 'exists')) {
                $done = true;
                DB::table($table)->insert($row);
            }
        });
    }

    public function test_two_taps_on_save_at_the_same_moment_are_not_an_error(): void
    {
        app(FeatureManager::class)->set('favourites', true);
        $this->racer('favourites', '`favourites`', ['user_id' => $this->buyer->id, 'ad_id' => $this->ad->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->buyer)->postJson("/favourites/{$this->ad->id}")->assertOk();
        $this->assertSame(1, DB::table('favourites')->count());
    }

    public function test_two_taps_on_follow_at_the_same_moment_are_not_an_error(): void
    {
        $this->racer('follows', '`follows`', ['follower_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'created_at' => now()]);

        $this->actingAs($this->buyer)->postJson("/follow/{$this->seller->id}")->assertOk();
        $this->assertSame(1, DB::table('follows')->count());
    }

    public function test_a_call_request_already_running_for_the_buyer_is_not_doubled(): void
    {
        $lock = Cache::lock('call-back:'.$this->buyer->id, 10);
        $this->assertTrue($lock->get());

        $this->actingAs($this->buyer)->post("/ads/{$this->ad->id}/call-back")->assertRedirect()
            ->assertSessionHas('status', 'You already asked this seller today. Please wait for the call.');
        $this->assertSame(0, InboxItem::count());

        $lock->release();
        $this->actingAs($this->buyer)->post("/ads/{$this->ad->id}/call-back")->assertRedirect();
        $this->assertSame(1, InboxItem::count());
    }

    public function test_people_cannot_touch_other_peoples_things(): void
    {
        app(FeatureManager::class)->set('saved_searches', true);
        $item = InboxItem::create(['user_id' => $this->seller->id, 'kind' => 'ad_approved', 'ad_id' => $this->ad->id, 'data' => ['title' => 'Private seller line'], 'created_at' => now()]);
        $criteria = ['q' => 'x', 'min' => null, 'max' => null, 'condition' => null, 'category_id' => null, 'city_id' => null, 'fields' => []];
        $search = $this->seller->savedSearches()->create(['label' => 'seller only', 'criteria' => $criteria, 'hash' => 'h', 'checked_until' => now()]);

        $this->actingAs($this->buyer)->get('/account/notifications')->assertOk()->assertDontSee('Private seller line');
        $this->assertNull($item->fresh()->read_at, "the buyer's visit did not read the seller's items");
        $this->actingAs($this->buyer)->patch("/account/searches/{$search->id}", ['muted' => '1'])->assertNotFound();
        $this->actingAs($this->buyer)->delete("/account/searches/{$search->id}")->assertNotFound();
        foreach (['close' => ['reason' => 'sold_here'], 'bump' => [], 'sold' => [], 'renew' => []] as $action => $data) {
            $this->actingAs($this->buyer)->post("/account/ads/{$this->ad->id}/{$action}", $data)->assertNotFound();
        }
        $this->assertTrue($this->ad->fresh()->isLive());
    }

    public function test_guests_are_sent_to_sign_in_for_every_new_signed_in_action(): void
    {
        foreach (['/account/notifications', '/account/notifications/settings', '/account/following'] as $url) {
            $this->get($url)->assertRedirect();
        }
        $this->post("/follow/{$this->seller->id}")->assertRedirect();
        $this->post("/ads/{$this->ad->id}/call-back")->assertRedirect();
        $this->post("/account/ads/{$this->ad->id}/bump")->assertRedirect();
        $this->assertSame(0, DB::table('follows')->count() + InboxItem::count());
    }

    public function test_unsafe_words_never_become_html(): void
    {
        $evil = '"><img src=x onerror=alert(1)>';
        $this->seller->forceFill(['name' => $evil])->save();
        $this->ad->forceFill(['title' => $evil.' car', 'description' => $evil])->save();
        InboxItem::create(['user_id' => $this->buyer->id, 'kind' => 'followed_ad', 'ad_id' => $this->ad->id, 'data' => ['title' => $evil, 'seller' => $evil], 'created_at' => now()]);

        foreach (['/seller/'.$this->seller->id, '/cars'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('<img src=x onerror=alert(1)>', false);
        }
        $this->actingAs($this->buyer)->get('/account/notifications')->assertOk()->assertDontSee('<img src=x onerror=alert(1)>', false);
        $this->getJson('/search-suggest?q='.urlencode('"><img'))->assertOk();
    }
}
