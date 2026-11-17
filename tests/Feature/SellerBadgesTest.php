<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\AdPhoto;
use App\Models\Category;
use App\Models\City;
use App\Models\Conversation;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use App\Support\Users\SellerBadges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SellerBadgesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
    }

    /** @param array<string, mixed> $attrs */
    private function ad(User $seller, array $attrs = []): Ad
    {
        return Ad::factory()->live()->create(array_merge([
            'user_id' => $seller->id, 'title' => 'Toyota Probox 2014 badge test',
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ], $attrs));
    }

    /** A buyer writes, the seller answers $minutes later. */
    private function chat(User $seller, Ad $ad, int $minutes): void
    {
        $buyer = User::factory()->completed()->create();
        $c = Conversation::create(['ad_id' => $ad->id, 'buyer_id' => $buyer->id, 'seller_id' => $seller->id]);
        $asked = now()->subDays(2);
        $c->messages()->create(['sender_id' => $buyer->id, 'body' => 'Is it still for sale?'])->forceFill(['created_at' => $asked])->save();
        $c->messages()->create(['sender_id' => $seller->id, 'body' => 'Yes'])->forceFill(['created_at' => $asked->copy()->addMinutes($minutes)])->save();
        $c->messages()->create(['sender_id' => $buyer->id, 'body' => 'Thanks'])->forceFill(['created_at' => $asked->copy()->addDays(1)])->save();
        $c->forceFill(['last_message_at' => now()->subDay()])->save();
    }

    public function test_years_or_months_on_iibsom(): void
    {
        $this->travelTo(now()->startOfDay()->setTime(12, 0));
        $this->assertNull(SellerBadges::tenure(User::factory()->create(['created_at' => now()->subDays(20)])));
        $this->assertSame(['unit' => 'months', 'count' => 5], SellerBadges::tenure(User::factory()->create(['created_at' => now()->subMonths(5)->subDay()])));
        $this->assertSame(['unit' => 'years', 'count' => 2], SellerBadges::tenure(User::factory()->create(['created_at' => now()->subMonths(30)])));

        $seller = User::factory()->completed()->create(['created_at' => now()->subYears(3)]);
        $ad = $this->ad($seller);
        $this->get($ad->url())->assertOk()->assertSee('3 years on iibsom');
        $this->get(str_replace(url('/'), url('/so'), $ad->url()))->assertOk()->assertSee('3 sano ku jira iibsom');

        app(FeatureManager::class)->set('sellers.badges', false);
        $this->get($ad->url())->assertOk()->assertDontSee('years on iibsom');
    }

    public function test_replies_fast_needs_chat_and_three_quick_answers(): void
    {
        $seller = User::factory()->completed()->create();
        $ad = $this->ad($seller);
        $this->chat($seller, $ad, 10);
        $this->chat($seller, $ad, 30);
        $this->assertNull(SellerBadges::medianReplyMinutes($seller), 'Only 2 answers: not enough.');
        $this->chat($seller, $ad, 600);
        $this->assertSame(30.0, SellerBadges::medianReplyMinutes($seller)); // middle of 10, 30, 600

        $this->assertFalse(SellerBadges::repliesFast($seller), 'Chat OFF: no badge.');
        app(FeatureManager::class)->set('chat', true);
        $this->assertTrue(SellerBadges::repliesFast($seller));
        $this->get($ad->url())->assertOk()->assertSee('Replies fast');

        // Slow seller.
        $slow = User::factory()->completed()->create();
        $slowAd = $this->ad($slow, ['title' => 'Slow seller car']);
        foreach ([90, 120, 300] as $m) {
            $this->chat($slow, $slowAd, $m);
        }
        $this->assertFalse(SellerBadges::repliesFast($slow));
        $this->get($slowAd->url())->assertOk()->assertDontSee('Replies fast');

        // Old chats (over 60 days) do not count.
        Conversation::where('seller_id', $seller->id)->update(['last_message_at' => now()->subDays(61)]);
        Cache::flush();
        $this->assertFalse(SellerBadges::repliesFast($seller));
    }

    public function test_cards_show_photo_count_and_popular(): void
    {
        $seller = User::factory()->completed()->create();
        $ad = $this->ad($seller, ['views' => 150]);
        AdPhoto::factory()->count(3)->create(['ad_id' => $ad->id, 'user_id' => $seller->id]);
        AdPhoto::factory()->create(['ad_id' => $ad->id, 'user_id' => $seller->id, 'status' => 'processing']);
        $one = $this->ad($seller, ['title' => 'Single photo car', 'views' => 5]);
        AdPhoto::factory()->create(['ad_id' => $one->id, 'user_id' => $seller->id]);

        $html = (string) $this->get('/cars')->assertOk()->getContent();
        $this->assertStringContainsString('3 photos', $html, 'Only ready photos count.');
        $this->assertStringNotContainsString('1 photo<', $html, 'One photo: no count.');
        $this->assertStringNotContainsString('>Popular', $html, 'Popular needs ad statistics.');

        app(FeatureManager::class)->set('ad_stats', true);
        $html = (string) $this->get('/cars')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'text-orange-700'), 'Only the ad with 100+ views.');
        $this->get('/')->assertOk()->assertSee('3 photos');

        app(FeatureManager::class)->set('ads.card_badges', false);
        $this->get('/cars')->assertOk()->assertDontSee('3 photos')->assertDontSee('text-orange-700', false);
    }

    public function test_header_has_my_ads_for_signed_in_people(): void
    {
        $user = User::factory()->completed()->create();
        $this->actingAs($user)->get('/')->assertOk()->assertSee('href="'.url('/account/ads').'" class="icon-btn', false);
        app(FeatureManager::class)->set('site.header_icons', false);
        $this->actingAs($user)->get('/')->assertOk()->assertDontSee('href="'.url('/account/ads').'" class="icon-btn', false);
        auth()->logout();
        $this->get('/')->assertOk()->assertDontSee('href="'.url('/account/ads').'"', false); // guests: none
    }

    public function test_old_view_numbers_are_kept_on_upgrade(): void
    {
        $ad = $this->ad(User::factory()->completed()->create());
        DB::table('ad_stats')->insert([
            ['ad_id' => $ad->id, 'day' => now()->subDays(2)->toDateString(), 'views' => 70],
            ['ad_id' => $ad->id, 'day' => now()->subDay()->toDateString(), 'views' => 45],
        ]);
        (require database_path('migrations/2026_11_17_280000_backfill_ad_views.php'))->up();
        $this->assertSame(115, $ad->fresh()?->views);
    }
}
