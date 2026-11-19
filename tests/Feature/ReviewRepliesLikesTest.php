<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ReviewController;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\Conversation;
use App\Models\SellerReview;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** PR 85: the seller's one answer to a rating, and "Helpful" likes. */
class ReviewRepliesLikesTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $seller;

    private User $reader;

    private SellerReview $review;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        app(FeatureManager::class)->set('chat', true);
        app(FeatureManager::class)->set('seller_ratings', true);
        $this->buyer = User::factory()->completed()->create(['name' => 'Hassan Buyer']);
        $this->seller = User::factory()->completed()->create(['name' => 'Amina Seller']);
        $this->reader = User::factory()->completed()->create(['name' => 'Omar Reader']);
        $this->review = $this->rating($this->buyer, 4, 'Good car, honest seller.');
    }

    private function rating(User $buyer, int $stars, ?string $comment): SellerReview
    {
        $ad = Ad::factory()->live()->create([
            'user_id' => $this->seller->id, 'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);
        $c = Conversation::create(['ad_id' => $ad->id, 'buyer_id' => $buyer->id, 'seller_id' => $this->seller->id]);
        $review = new SellerReview(['stars' => $stars, 'comment' => $comment]);
        $review->forceFill(['seller_id' => $this->seller->id, 'buyer_id' => $buyer->id, 'conversation_id' => $c->id, 'ad_id' => $ad->id])->save();
        SellerReview::recount($this->seller->id);

        return $review;
    }

    private function page(?User $as = null, string $prefix = ''): string
    {
        $url = url($prefix."/seller/{$this->seller->id}/reviews");
        $res = $as ? $this->actingAs($as)->get($url) : $this->get($url);

        return (string) $res->assertOk()->getContent();
    }

    private function reply(string $text, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->seller)->from(url("/seller/{$this->seller->id}/reviews"))
            ->post("/reviews/{$this->review->id}/reply", ['reply' => $text, 'review_id' => $this->review->id]);
    }

    // ---- Seller's answer ----

    public function test_seller_sees_the_answer_form_others_do_not(): void
    {
        $this->assertStringContainsString('data-review-reply-form', $this->page($this->seller));
        $this->assertStringNotContainsString('data-review-reply-form', $this->page($this->reader));
        $this->assertStringNotContainsString('data-review-reply-form', $this->page($this->buyer));
        auth()->logout();
        $this->assertStringNotContainsString('data-review-reply-form', $this->page());
    }

    public function test_seller_answers_once_and_everyone_sees_it(): void
    {
        $this->reply("  <b>Thank you</b>, Hassan!\nCome again.  ")
            ->assertRedirect(url("/seller/{$this->seller->id}/reviews").'#review-'.$this->review->id)->assertSessionHas('status');

        $this->review->refresh();
        $this->assertSame("Thank you, Hassan!\nCome again.", $this->review->reply);
        $this->assertNotNull($this->review->replied_at);

        auth()->logout();
        $html = $this->page();
        $this->assertStringContainsString('Answer from Amina', $html);
        $this->assertStringContainsString("Thank you, Hassan!\nCome again.", $html);
        $this->assertStringNotContainsString('<b>', $html);

        // One answer only; the form is gone.
        $this->reply('Changed my mind')->assertSessionHasErrors(['reply' => __('ads.ratings.reply_once')]);
        $this->assertSame("Thank you, Hassan!\nCome again.", $this->review->fresh()->reply);
        $this->assertStringNotContainsString('data-review-reply-form', $this->page($this->seller));
    }

    public function test_only_the_seller_of_that_rating_can_answer(): void
    {
        $this->reply('Not mine', $this->reader)->assertForbidden();
        $this->reply('Not mine', $this->buyer)->assertForbidden();
        auth()->logout();
        $this->post("/reviews/{$this->review->id}/reply", ['reply' => 'Guest'])->assertRedirect();
        $this->assertNull($this->review->fresh()->reply);
    }

    public function test_answers_are_checked_like_ads(): void
    {
        $this->reply('Call me on whatsapp www.example.com')->assertSessionHasErrors('reply');
        $this->reply('send money first')->assertSessionHasErrors('reply');
        $this->reply('fresh khat here')->assertSessionHasErrors('reply');
        $this->reply('   <i></i>  ')->assertSessionHasErrors('reply');
        $this->reply(str_repeat('a', SellerReview::COMMENT_MAX + 1))->assertSessionHasErrors('reply');
        $this->assertNull($this->review->fresh()->reply);

        // The form comes back open with the text.
        $this->reply('send money first');
        $html = $this->page($this->seller);
        $this->assertStringContainsString('send money first</textarea>', $html);
        $this->assertMatchesRegularExpression('/<details class="mt-3"\s+open\s*>/', $html);
    }

    public function test_hidden_ratings_cannot_be_answered_or_liked(): void
    {
        $this->review->forceFill(['hidden_at' => now()])->save();
        $this->reply('Hello')->assertNotFound();
        $this->actingAs($this->reader)->postJson("/reviews/{$this->review->id}/like")->assertNotFound();
    }

    public function test_answer_switch_off(): void
    {
        $this->review->forceFill(['reply' => 'Old answer', 'replied_at' => now()])->save();
        app(FeatureManager::class)->set('ratings.replies', false);
        $this->reply('Hello')->assertNotFound();
        $html = $this->page($this->seller);
        $this->assertStringNotContainsString('data-review-reply', $html);
        $this->assertStringNotContainsString('Old answer', $html);
    }

    public function test_a_changed_rating_drops_the_old_answer_and_likes(): void
    {
        $this->review->forceFill(['reply' => 'Thanks!', 'replied_at' => now()])->save();
        $this->actingAs($this->reader)->postJson("/reviews/{$this->review->id}/like")->assertOk();

        // The seller answered in the chat, so the buyer may rate (again).
        $c = Conversation::findOrFail($this->review->conversation_id);
        $c->messages()->create(['sender_id' => $this->seller->id, 'body' => 'Hi']);

        // Same stars and words: nothing changes.
        $this->actingAs($this->buyer)->post("/messages/{$c->id}/review", ['stars' => 4, 'comment' => 'Good car, honest seller.'])->assertRedirect();
        $this->assertSame('Thanks!', $this->review->fresh()->reply);
        $this->assertSame(1, $this->review->fresh()->likes_count);

        $this->actingAs($this->buyer)->post("/messages/{$c->id}/review", ['stars' => 2, 'comment' => 'The car broke down.'])->assertRedirect();
        $fresh = $this->review->fresh();
        $this->assertNull($fresh->reply);
        $this->assertNull($fresh->replied_at);
        $this->assertSame(0, $fresh->likes_count);
        $this->assertSame(0, DB::table('review_likes')->count());
    }

    // ---- Helpful ----

    public function test_like_and_unlike(): void
    {
        $this->actingAs($this->reader)->postJson("/reviews/{$this->review->id}/like")->assertOk()->assertExactJson(['liked' => true, 'count' => 1]);
        $other = User::factory()->completed()->create();
        $this->actingAs($other)->postJson("/reviews/{$this->review->id}/like")->assertExactJson(['liked' => true, 'count' => 2]);

        $html = $this->page($this->reader);
        $this->assertMatchesRegularExpression('/aria-pressed="true"[^>]*>\s*<svg/', $html);
        $this->assertStringContainsString('data-like-count>2</span>', $html);

        $this->actingAs($this->reader)->postJson("/reviews/{$this->review->id}/like")->assertExactJson(['liked' => false, 'count' => 1]);
        $this->assertSame(1, DB::table('review_likes')->count());
        $this->assertSame(1, $this->review->fresh()->likes_count);

        // Without JavaScript: back to the rating.
        $this->actingAs($this->reader)->post("/reviews/{$this->review->id}/like")
            ->assertRedirect(url("/seller/{$this->seller->id}/reviews").'#review-'.$this->review->id);
        $this->assertSame(2, $this->review->fresh()->likes_count);
    }

    public function test_seller_and_author_cannot_like_and_see_the_count_only(): void
    {
        $this->actingAs($this->seller)->postJson("/reviews/{$this->review->id}/like")->assertForbidden();
        $this->actingAs($this->buyer)->postJson("/reviews/{$this->review->id}/like")->assertForbidden();
        $this->assertSame(0, DB::table('review_likes')->count());

        $this->actingAs($this->reader)->postJson("/reviews/{$this->review->id}/like");
        $html = $this->page($this->seller);
        $this->assertStringNotContainsString('data-review-like', $html);
        $this->assertStringContainsString('1 person found this helpful', $html);
    }

    public function test_guests_see_a_sign_in_link(): void
    {
        $html = $this->page();
        $this->assertStringNotContainsString('data-review-like', $html);
        $this->assertStringContainsString('title="Sign in to mark this rating as helpful"', $html);
        $this->post("/reviews/{$this->review->id}/like")->assertRedirect();
        $this->assertSame(0, DB::table('review_likes')->count());
    }

    public function test_likes_switch_off(): void
    {
        app(FeatureManager::class)->set('ratings.likes', false);
        $this->actingAs($this->reader)->postJson("/reviews/{$this->review->id}/like")->assertNotFound();
        $this->assertStringNotContainsString('data-review-like', $this->page($this->reader));
    }

    public function test_somali_words(): void
    {
        $this->review->forceFill(['reply' => 'Mahadsanid', 'replied_at' => now()])->save();
        $html = $this->page($this->reader, '/so');
        $this->assertStringContainsString('Jawaabta Amina', $html);
        $this->assertStringContainsString('Waxtar leh', $html);
        $this->assertStringNotContainsString('ads.ratings.', $html);
    }

    public function test_ratings_page_query_count_does_not_grow_with_likes(): void
    {
        foreach (range(1, 8) as $i) {
            $r = $this->rating(User::factory()->completed()->create(), 5, 'Fine '.$i);
            DB::table('review_likes')->insert(['review_id' => $r->id, 'user_id' => $this->reader->id, 'created_at' => now()]);
            $r->forceFill(['likes_count' => 1, 'reply' => 'Thanks '.$i, 'replied_at' => now()])->save();
        }
        $this->page($this->reader);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->page($this->reader);
        $n = collect(DB::getQueryLog())->reject(fn ($q) => str_contains($q['query'], '`cache`'))->count();
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(12, $n);
    }

    public function test_admin_list_shows_answer_and_likes(): void
    {
        $this->review->forceFill(['reply' => 'Thanks!', 'replied_at' => now(), 'likes_count' => 3])->save();
        $row = collect(app(ReviewController::class)->index(request())->getData(true)['data'])->firstWhere('id', $this->review->id);
        $this->assertSame('Thanks!', $row['reply']);
        $this->assertSame(3, $row['likes_count']);
        $this->assertNotNull($row['replied_at']);
    }
}
