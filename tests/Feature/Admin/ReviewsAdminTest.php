<?php

namespace Tests\Feature\Admin;

use App\Models\Ad;
use App\Models\AdminActivity;
use App\Models\Category;
use App\Models\City;
use App\Models\Conversation;
use App\Models\SellerReview;
use App\Models\User;
use App\Support\Features\FeatureManager;

class ReviewsAdminTest extends AdminTestCase
{
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        app(FeatureManager::class)->set('chat', true);
        app(FeatureManager::class)->set('seller_ratings', true);
        $this->seller = User::factory()->completed()->create(['name' => 'Amina Seller']);
    }

    private function review(int $stars, string $comment = 'Fine'): SellerReview
    {
        $buyer = User::factory()->completed()->create();
        $ad = Ad::factory()->live()->create([
            'user_id' => $this->seller->id,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);
        $c = Conversation::create(['ad_id' => $ad->id, 'buyer_id' => $buyer->id, 'seller_id' => $this->seller->id]);
        $review = new SellerReview(['stars' => $stars, 'comment' => $comment]);
        $review->forceFill(['seller_id' => $this->seller->id, 'buyer_id' => $buyer->id, 'conversation_id' => $c->id, 'ad_id' => $ad->id])->save();
        SellerReview::recount($this->seller->id);

        return $review;
    }

    public function test_switch_and_permission(): void
    {
        $this->asStaff('support');
        $this->getJson('/admin/api/reviews')->assertForbidden();
        $this->getJson('/admin/api/me')->assertJsonPath('user.seller_ratings', true);

        $this->asStaff('moderator');
        $this->getJson('/admin/api/reviews')->assertOk();
        app(FeatureManager::class)->set('seller_ratings', false);
        $this->getJson('/admin/api/reviews')->assertNotFound();
    }

    public function test_list_filter_hide_and_show_again(): void
    {
        $bad = $this->review(1, 'Rude words');
        $this->review(5, 'Great');
        $this->assertSame([2, 6], [$this->seller->fresh()?->rating_count, $this->seller->fresh()?->rating_sum]);
        $this->asStaff('moderator');

        $this->getJson('/admin/api/reviews')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.seller.name', 'Amina Seller');
        $this->getJson('/admin/api/reviews?stars=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.comment', 'Rude words');

        $this->postJson("/admin/api/reviews/{$bad->id}/hide", [])->assertUnprocessable();
        $this->postJson("/admin/api/reviews/{$bad->id}/hide", ['reason' => 'Rude words'])->assertOk();
        $this->assertSame([1, 5], [$this->seller->fresh()?->rating_count, $this->seller->fresh()?->rating_sum]);
        $this->getJson('/admin/api/reviews')->assertJsonCount(1, 'data');
        $this->getJson('/admin/api/reviews?show=hidden')->assertJsonCount(1, 'data')->assertJsonPath('data.0.hidden_reason', 'Rude words');
        $this->assertTrue(AdminActivity::where('action', 'review.hidden')->exists());
        $this->get("/seller/{$this->seller->id}/reviews")->assertOk()->assertDontSee('Rude words')->assertSee('Great');

        $this->postJson("/admin/api/reviews/{$bad->id}/unhide")->assertOk();
        $this->assertSame([2, 6], [$this->seller->fresh()?->rating_count, $this->seller->fresh()?->rating_sum]);
    }
}
