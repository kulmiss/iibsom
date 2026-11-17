<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\Conversation;
use App\Models\SellerReview;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SellerRatingsTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $seller;

    private Ad $ad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        app(FeatureManager::class)->set('chat', true);
        app(FeatureManager::class)->set('seller_ratings', true);
        $this->buyer = User::factory()->completed()->create(['name' => 'Hassan Buyer']);
        $this->seller = User::factory()->completed()->create(['name' => 'Amina Seller']);
        $this->ad = Ad::factory()->live()->create([
            'user_id' => $this->seller->id, 'title' => 'Toyota Vitz 2012 rated',
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);
    }

    /** A chat about the ad; the seller answers when $answered. */
    private function chat(bool $answered = true, ?User $buyer = null): Conversation
    {
        $buyer ??= $this->buyer;
        $this->actingAs($buyer)->post("/ads/{$this->ad->id}/chat")->assertRedirect();
        $c = Conversation::where('buyer_id', $buyer->id)->firstOrFail();
        $this->actingAs($buyer)->postJson("/messages/{$c->id}", ['body' => 'Is it still available?'])->assertCreated();
        if ($answered) {
            $this->actingAs($this->seller)->postJson("/messages/{$c->id}", ['body' => 'Yes, come and see it.'])->assertCreated();
        }

        return $c;
    }

    private function rate(Conversation $c, int $stars, ?string $comment = null, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->buyer)->post("/messages/{$c->id}/review", ['stars' => $stars, 'comment' => $comment]);
    }

    public function test_off_by_default_and_nothing_shows(): void
    {
        $this->assertFalse(config('features')['seller_ratings']['default']);
        $c = $this->chat();
        app(FeatureManager::class)->set('seller_ratings', false);

        $this->rate($c, 5)->assertNotFound();
        $this->actingAs($this->buyer)->get("/messages/{$c->id}")->assertOk()->assertDontSee('Rate this seller');
        $this->get("/seller/{$this->seller->id}/reviews")->assertNotFound();
        $this->assertSame(0, SellerReview::count());
    }

    public function test_only_after_the_seller_answered(): void
    {
        $c = $this->chat(answered: false);
        $this->actingAs($this->buyer)->get("/messages/{$c->id}")->assertOk()
            ->assertDontSee('Rate this seller')->assertSee('You can rate the seller after they answer you.');
        $this->rate($c, 5)->assertForbidden();

        $this->actingAs($this->seller)->postJson("/messages/{$c->id}", ['body' => 'Hello'])->assertCreated();
        $this->actingAs($this->buyer)->get("/messages/{$c->id}")->assertOk()->assertSee('Rate this seller');
        $this->rate($c, 4, 'Honest seller')->assertRedirect(url("/messages/{$c->id}"));
        $this->assertSame(1, SellerReview::count());
    }

    public function test_seller_and_strangers_cannot_rate(): void
    {
        $c = $this->chat();
        $this->rate($c, 1, as: $this->seller)->assertForbidden(); // the seller can't rate himself
        $this->actingAs($this->seller)->get("/messages/{$c->id}")->assertOk()->assertDontSee('Rate this seller');
        $this->rate($c, 1, as: User::factory()->completed()->create())->assertNotFound(); // not in this chat
        $this->assertSame(0, SellerReview::count());
    }

    public function test_one_rating_per_chat_can_be_changed_and_average_is_kept(): void
    {
        $c = $this->chat();
        $this->rate($c, 2, 'Slow to answer')->assertRedirect();
        $this->rate($c, 4, 'He came in the end')->assertRedirect();
        $this->assertSame(1, SellerReview::count());
        $this->assertSame([1, 4], [$this->seller->fresh()?->rating_count, $this->seller->fresh()?->rating_sum]);

        $other = User::factory()->completed()->create();
        $this->rate($this->chat(buyer: $other), 5, as: $other)->assertRedirect();
        $seller = $this->seller->fresh();
        $this->assertSame(4.5, $seller?->rating());

        // Shown next to the seller on the ad page (also for guests: cached page refreshed).
        $this->get($this->ad->url())->assertOk()->assertSee('4.5')->assertSee('2 ratings');
        $path = (string) parse_url($this->ad->url(), PHP_URL_PATH);
        $this->get('/so'.$path)->assertOk()->assertSee('2 qiimaynood');
    }

    public function test_bad_input_and_bad_words(): void
    {
        app(SettingsManager::class)->set('safety.bad_words', ['scammer']);
        $c = $this->chat();
        $this->rate($c, 0)->assertSessionHasErrors('stars');
        $this->rate($c, 6)->assertSessionHasErrors('stars');
        $this->rate($c, 3, str_repeat('a', SellerReview::COMMENT_MAX + 1))->assertSessionHasErrors('comment');
        $this->rate($c, 3, 'Visit www.cheap-cars.com now')->assertSessionHasErrors('comment');
        $this->rate($c, 3, 'He is a scammer')->assertSessionHasErrors('comment');
        $this->assertSame(0, SellerReview::count());

        $this->rate($c, 3, '<b>Ok</b> seller')->assertRedirect();
        $this->assertSame('Ok seller', SellerReview::firstOrFail()->comment);
    }

    public function test_public_ratings_page(): void
    {
        $this->get("/seller/{$this->seller->id}/reviews")->assertNotFound(); // no ratings yet
        $this->rate($this->chat(), 5, '<script>alert(1)</script> Great')->assertRedirect();

        $this->get("/seller/{$this->seller->id}/reviews")->assertOk()
            ->assertSee('Ratings for Amina Seller')->assertSee('5.0 out of 5')
            ->assertSee('Hassan')->assertDontSee('Hassan Buyer') // buyer's first name only
            ->assertSee('Great')->assertDontSee('<script>alert(1)', false)
            ->assertSee('About: Toyota Vitz 2012 rated')
            ->assertSee('noindex', false);
        $this->assertEmpty($this->get("/seller/{$this->seller->id}/reviews")->headers->getCookies(), 'Guest page: no cookies.');

        $this->seller->forceFill(['banned_at' => now()])->save();
        $this->get("/seller/{$this->seller->id}/reviews")->assertNotFound();
    }

    public function test_buyer_sees_hidden_note_and_editing_does_not_unhide(): void
    {
        $c = $this->chat();
        $this->rate($c, 1, 'Bad')->assertRedirect();
        $review = SellerReview::firstOrFail();
        $review->forceFill(['hidden_at' => now(), 'hidden_reason' => 'Rude'])->save();
        SellerReview::recount($this->seller->id);
        $this->assertSame(0, $this->seller->fresh()?->rating_count);

        $this->actingAs($this->buyer)->get("/messages/{$c->id}")->assertOk()->assertSee('Our team hid this rating');
        $this->rate($c, 2, 'Still bad')->assertRedirect();
        $this->assertNotNull($review->fresh()?->hidden_at);
        $this->assertSame(0, $this->seller->fresh()?->rating_count);
    }
}
