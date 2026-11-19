<?php

namespace Tests\Feature;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** PR 85: write to the seller in a box on the ad page (switch "chat.ad_box", needs "chat"). */
class AdMessageBoxTest extends TestCase
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
        $this->buyer = User::factory()->completed()->create(['name' => 'Hassan Buyer']);
        $this->seller = User::factory()->completed()->create(['name' => 'Amina Seller']);
        $this->ad = Ad::factory()->live()->create([
            'user_id' => $this->seller->id, 'title' => 'Toyota Vitz 2012 box',
            'category_id' => Category::where('slug', 'cars')->value('id'),
        ]);
    }

    private function send(string $text): TestResponse
    {
        return $this->actingAs($this->buyer)->from($this->ad->url())->post("/ads/{$this->ad->id}/chat", ['message' => $text]);
    }

    public function test_box_is_shown_to_signed_in_buyers_only(): void
    {
        $this->actingAs($this->buyer)->get($this->ad->url())->assertOk()
            ->assertSee('data-ad-message', false)->assertSee('Write to Amina Seller')->assertSee('name="message"', false);

        $this->actingAs($this->seller)->get($this->ad->url())->assertOk()->assertDontSee('data-ad-message', false);
        auth()->logout();
        $this->get($this->ad->url())->assertOk()->assertDontSee('data-ad-message', false);
    }

    public function test_box_follows_its_switch_and_chat(): void
    {
        app(FeatureManager::class)->set('chat.ad_box', false);
        $this->actingAs($this->buyer)->get($this->ad->url())->assertDontSee('data-ad-message', false);

        app(FeatureManager::class)->set('chat.ad_box', true);
        app(FeatureManager::class)->set('chat', false);
        $this->actingAs($this->buyer)->get($this->ad->url())->assertDontSee('data-ad-message', false);
        $this->send('Hello')->assertNotFound();
        $this->assertSame(0, Conversation::count());
    }

    public function test_somali_words(): void
    {
        $this->actingAs($this->buyer)->get(str_replace(url('/'), url('/so'), $this->ad->url()))->assertOk()
            ->assertSee('U qor Amina Seller')->assertSee('Dir fariinta')->assertDontSee('chat.ad_box.');
    }

    public function test_sends_the_message_and_opens_the_chat(): void
    {
        $this->send("  Hello, is it still available?\r\nThanks  ")->assertRedirect();

        $c = Conversation::sole();
        $m = Message::sole();
        $this->assertSame([$this->ad->id, $this->buyer->id, $this->seller->id], [$c->ad_id, $c->buyer_id, $c->seller_id]);
        $this->assertSame("Hello, is it still available?\nThanks", $m->body);
        $this->assertSame($this->buyer->id, $m->sender_id);
        $this->assertSame($m->id, $c->fresh()->last_message_id);
        $this->assertSame($m->id, $c->fresh()->buyer_read_id);
        $this->assertSame(1, Conversation::unreadCount($this->seller));

        // A second message goes into the same chat.
        $this->send('Can I see it today?')->assertRedirect(lroute('chat.show', ['conversation' => $c->id]).'#m'.Message::max('id'));
        $this->assertSame(1, Conversation::count());
        $this->assertSame(2, Message::count());
    }

    public function test_refused_messages_leave_nothing_behind_and_come_back_with_the_text(): void
    {
        $this->send('send money first please')->assertRedirect($this->ad->url().'#message')->assertSessionHasErrors('message');
        $this->send('   ')->assertSessionHasErrors('message');
        $this->send(str_repeat('a', Message::MAX_LENGTH + 1))->assertSessionHasErrors('message');
        $this->assertSame(0, Conversation::count());
        $this->assertSame(0, Message::count());

        // The ad page shows the note and keeps what was typed.
        $this->send('send money first please');
        $this->actingAs($this->buyer)->get($this->ad->url())
            ->assertSee('Your message has words that are not allowed. Please change it.')
            ->assertSee('send money first please</textarea>', false)
            ->assertSee('aria-invalid="true"', false);
    }

    public function test_drug_words_are_refused_and_the_sender_marked_for_review(): void
    {
        $this->send('fresh khat today')->assertSessionHasErrors('message');

        $this->assertSame(0, Conversation::count());
        $this->assertSame(0, Message::count());
        $this->buyer->refresh();
        $this->assertSame('drugs', $this->buyer->flag);
        $this->assertSame('khat', $this->buyer->flag_word);
        $this->assertNull($this->buyer->banned_at);
    }

    public function test_blocked_chat_stays_blocked(): void
    {
        $c = Conversation::create(['ad_id' => $this->ad->id, 'buyer_id' => $this->buyer->id, 'seller_id' => $this->seller->id]);
        $c->forceFill(['blocked_by' => $this->seller->id])->save();

        $this->send('Hello again')->assertSessionHasErrors(['message' => __('chat.blocked')]);
        $this->assertSame(0, Message::count());
    }

    public function test_not_on_your_own_ad_a_banned_seller_or_an_ad_that_is_not_live(): void
    {
        $this->actingAs($this->seller)->post("/ads/{$this->ad->id}/chat", ['message' => 'Hi'])->assertForbidden();

        $this->seller->forceFill(['banned_at' => now()])->save();
        $this->send('Hello')->assertNotFound();
        $this->seller->forceFill(['banned_at' => null])->save();

        $this->ad->forceFill(['status' => AdStatus::Sold])->save();
        $this->send('Hello')->assertNotFound();
        $this->assertSame(0, Message::count());
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->post("/ads/{$this->ad->id}/chat", ['message' => 'Hello'])->assertRedirect();
        $this->assertSame(0, Message::count());
    }

    public function test_with_the_switch_off_the_text_is_never_sent(): void
    {
        app(FeatureManager::class)->set('chat.ad_box', false);
        $this->send('Hello')->assertRedirect();
        $this->assertSame(0, Message::count());
    }
}
