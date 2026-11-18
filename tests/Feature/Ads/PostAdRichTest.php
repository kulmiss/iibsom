<?php

namespace Tests\Feature\Ads;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** PR 78: a friendlier "Post an ad": steps, category search, tips, live preview, share after posting. */
class PostAdRichTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->seller = User::factory()->completed()->create(['city_id' => City::where('slug', 'mogadishu')->value('id')]);
    }

    private function ad(AdStatus $status): Ad
    {
        return Ad::factory()->create([
            'user_id' => $this->seller->id, 'status' => $status, 'title' => 'Toyota Vitz 2012',
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'published_at' => $status === AdStatus::Live ? now() : null,
        ]);
    }

    // ---- Steps ----

    public function test_steps_show_where_the_seller_is(): void
    {
        $this->actingAs($this->seller);
        $choose = $this->get('/post')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/aria-current="step"\s*>\s*<span[^>]*>1</', $choose);

        $form = $this->get('/post/cars')->assertOk()->getContent();
        $this->assertStringContainsString('data-post-steps', $form);
        $this->assertMatchesRegularExpression('/aria-current="step"\s*>\s*<span[^>]*>2</', $form);

        $done = $this->get('/post/done/'.$this->ad(AdStatus::Pending)->id)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/aria-current="step"\s*>\s*<span[^>]*>3</', $done);

        $this->get('/so/post')->assertSee(__('ads.post.step_category', [], 'so'));
    }

    public function test_steps_switch_off_keeps_the_old_line(): void
    {
        app(FeatureManager::class)->set('post.steps', false);
        $this->actingAs($this->seller)->get('/post')->assertDontSee('data-post-steps', false)->assertSee('Step 1 of 2');
    }

    // ---- Step 1: category search ----

    public function test_category_search_knows_english_and_somali_names(): void
    {
        $html = $this->actingAs($this->seller)->get('/post')->assertOk()->getContent();

        $this->assertStringContainsString('hidden [.js_&]:block" data-cat-search-box', $html); // only with JavaScript, no jump
        $this->assertMatchesRegularExpression('/data-cat-item data-names="[^"]*cars[^"]*baabuur/', $html);
        $this->assertStringContainsString(__('ads.post.no_category'), $html);

        app(FeatureManager::class)->set('post.category_search', false);
        $this->actingAs($this->seller)->get('/post')->assertDontSee('data-cat-search', false);
    }

    // ---- Step 2: tips, preview, button ----

    public function test_tips_are_open_for_a_new_ad_and_closed_when_editing(): void
    {
        $this->actingAs($this->seller);
        $this->assertMatchesRegularExpression('/data-post-tips open>/', (string) $this->get('/post/cars')->getContent());
        $this->get('/post/cars')->assertSee('Tips for an ad that sells')->assertSee('Add 3 or more clear photos');
        $this->get('/so/post/cars')->assertSee(__('ads.post.tip_1', [], 'so'));

        $live = $this->ad(AdStatus::Live);
        $edit = $this->get('/account/ads/'.$live->id.'/edit')->assertOk()->getContent();
        $this->assertStringContainsString('Edit ad', $edit, 'edit page');
        $this->assertStringContainsString('data-post-tips', $edit);
        $this->assertDoesNotMatchRegularExpression('/data-post-tips\s*open/', $edit, 'closed when editing');

        app(FeatureManager::class)->set('post.tips', false);
        $this->assertStringNotContainsString('data-post-tips', (string) $this->get('/post/cars')->getContent(), 'switch off');
    }

    public function test_live_preview_is_ready_for_the_script(): void
    {
        $html = $this->actingAs($this->seller)->get('/post/cars')->assertOk()->getContent();
        foreach (['data-preview hidden', 'data-preview-name', 'data-preview-price', 'data-preview-photo', 'data-negotiable="Negotiable"'] as $part) {
            $this->assertStringContainsString($part, $html);
        }

        app(FeatureManager::class)->set('post.preview', false);
        $this->actingAs($this->seller)->get('/post/cars')->assertDontSee('data-preview', false);
    }

    public function test_the_post_button_no_longer_floats_over_the_fields(): void
    {
        $html = $this->actingAs($this->seller)->get('/post/cars')->getContent();
        $this->assertStringContainsString('data-post-submit', $html);
        $this->assertStringNotContainsString('sticky bottom-16', $html);
    }

    // ---- Step 3: done ----

    public function test_live_ad_done_page_offers_sharing_and_the_free_move_to_top(): void
    {
        $ad = $this->ad(AdStatus::Live);
        $html = $this->actingAs($this->seller)->get('/post/done/'.$ad->id)->assertOk()->getContent();

        $this->assertStringContainsString('data-done-view', $html);
        $this->assertStringContainsString('https://wa.me/?text='.rawurlencode($ad->title.' '.$ad->url()), $html);
        $this->assertStringContainsString('data-url="'.$ad->url().'"', $html);
        $this->assertStringContainsString('data-done-bump', $html);
        $this->get('/so/post/done/'.$ad->id)->assertSee(__('ads.done.share_title', [], 'so'));
    }

    public function test_waiting_ad_cannot_be_shared_yet(): void
    {
        $ad = $this->ad(AdStatus::Pending);
        $html = $this->actingAs($this->seller)->get('/post/done/'.$ad->id)->assertOk()->getContent();

        $this->assertStringContainsString('You can share your ad as soon as it is live', $html);
        $this->assertStringNotContainsString('wa.me', $html);
        $this->assertStringNotContainsString('data-done-view', $html);
    }

    public function test_done_page_switches(): void
    {
        $ad = $this->ad(AdStatus::Live);
        app(FeatureManager::class)->set('ads.free_bump', false);
        $this->actingAs($this->seller)->get('/post/done/'.$ad->id)->assertSee('data-done-share', false)->assertDontSee('data-done-bump', false);

        app(FeatureManager::class)->set('post.done_plus', false);
        $this->actingAs($this->seller)->get('/post/done/'.$ad->id)->assertOk()->assertDontSee('data-done-share', false);
    }
}
