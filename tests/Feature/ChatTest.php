<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ChatTest extends TestCase
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
        $this->buyer = User::factory()->completed()->create(['name' => 'Hassan Buyer']);
        $this->seller = User::factory()->completed()->create(['name' => 'Amina Seller']);
        $this->ad = Ad::factory()->live()->create([
            'user_id' => $this->seller->id, 'title' => 'Toyota Vitz 2012 for chat',
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);
    }

    private function on(): void
    {
        app(FeatureManager::class)->set('chat', true);
    }

    private function start(): Conversation
    {
        $this->actingAs($this->buyer)->post("/ads/{$this->ad->id}/chat")->assertRedirect();

        return Conversation::firstOrFail();
    }

    private function say(User $who, Conversation $c, string $text): TestResponse
    {
        return $this->actingAs($who)->postJson("/messages/{$c->id}", ['body' => $text]);
    }

    public function test_off_by_default(): void
    {
        $this->assertFalse(feature('chat'));
        $this->actingAs($this->buyer)->get('/messages')->assertNotFound();
        $this->actingAs($this->buyer)->post("/ads/{$this->ad->id}/chat")->assertNotFound();
        $this->actingAs($this->buyer)->get($this->ad->url())->assertOk()->assertDontSee('Chat with seller');
    }

    public function test_buyer_starts_a_chat_and_both_talk(): void
    {
        $this->on();
        $this->actingAs($this->buyer)->get($this->ad->url())->assertOk()->assertSee('Chat with seller');

        $c = $this->start();
        $this->actingAs($this->buyer)->post("/ads/{$this->ad->id}/chat")->assertRedirect(url("/messages/{$c->id}"));
        $this->assertSame(1, Conversation::count(), 'Same ad, same buyer: same conversation.');
        $this->actingAs($this->buyer)->get("/messages/{$c->id}")->assertOk()
            ->assertSee('Amina Seller')->assertSee('About: Toyota Vitz 2012 for chat')->assertSee('noindex', false);

        $this->say($this->buyer, $c, "Salaan! Is it still available?\nPrice?")->assertCreated()->assertJsonPath('message.mine', true);
        $this->say($this->seller, $c, 'Yes, $6,400.')->assertCreated();

        $this->actingAs($this->buyer)->get("/messages/{$c->id}")->assertSeeInOrder(['Salaan! Is it still available?', 'Yes, $6,400.']);
        $this->actingAs($this->seller)->get('/so/messages')->assertOk()->assertSee('Fariimaha')->assertSee('Hassan Buyer');
    }

    public function test_new_messages_arrive_by_asking_and_unread_is_shown(): void
    {
        $this->on();
        $c = $this->start();
        $first = $this->say($this->buyer, $c, 'Hello')->json('message.id');

        // Seller sees a "new" mark in the menu and the inbox.
        $this->actingAs($this->seller)->get('/')->assertOk()->assertSee('1 chat with new messages');
        app(FeatureManager::class)->set('site.header_icons', false); // the old dot
        $this->actingAs($this->seller)->get('/')->assertOk()->assertSee('aria-label="New"', false)->assertDontSee('1 chat with new messages');
        app(FeatureManager::class)->set('site.header_icons', true);
        $this->actingAs($this->seller)->get('/messages')->assertSee('New');

        $this->actingAs($this->buyer)->getJson("/messages/{$c->id}/since?after={$first}")->assertOk()->assertJsonCount(0, 'messages');
        $this->say($this->seller, $c, 'Hi, how can I help?');
        $this->actingAs($this->buyer)->getJson("/messages/{$c->id}/since?after={$first}")->assertOk()
            ->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.body', 'Hi, how can I help?')->assertJsonPath('messages.0.mine', false);

        // The buyer writes last; only opening the chat marks it read for the seller.
        $this->say($this->buyer, $c, 'Can I see it today?');
        $this->assertSame(1, Conversation::unreadCount($this->seller->fresh()));
        $this->actingAs($this->seller)->get("/messages/{$c->id}");
        $this->assertSame(0, Conversation::unreadCount($this->seller->fresh()));
        $this->actingAs($this->seller)->get('/')->assertDontSee('aria-label="New"', false)->assertDontSee('chat with new messages');
    }

    public function test_message_times_are_shown_in_somalia_time(): void
    {
        $this->on();
        $c = $this->start();
        $this->travelTo(now('UTC')->setTime(6, 37));

        $this->say($this->buyer, $c, 'Morning')->assertJsonPath('message.time', '09:37');
        $this->actingAs($this->seller)->get("/messages/{$c->id}")->assertSee('09:37')->assertDontSee('06:37');
    }

    public function test_messages_are_escaped_and_never_run(): void
    {
        $this->on();
        $c = $this->start();
        $this->say($this->buyer, $c, '<script>alert(1)</script> <b>hi</b>')->assertCreated();

        $this->actingAs($this->seller)->get("/messages/{$c->id}")->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_strangers_cannot_read_or_write(): void
    {
        $this->on();
        $c = $this->start();
        $this->say($this->buyer, $c, 'private');
        $stranger = User::factory()->completed()->create();

        $this->actingAs($stranger)->get("/messages/{$c->id}")->assertNotFound();
        $this->actingAs($stranger)->getJson("/messages/{$c->id}/since")->assertNotFound();
        $this->say($stranger, $c, 'hi')->assertNotFound();
        $this->actingAs($stranger)->get('/messages')->assertOk()->assertDontSee('private');
        auth()->logout();
        $this->get("/messages/{$c->id}")->assertRedirect(url('/login'));
    }

    public function test_no_chat_with_yourself_hidden_ads_or_banned_sellers(): void
    {
        $this->on();
        $this->actingAs($this->seller)->get($this->ad->url())->assertDontSee('Chat with seller');
        $this->actingAs($this->seller)->post("/ads/{$this->ad->id}/chat")->assertForbidden();

        $this->seller->forceFill(['banned_at' => now()])->save();
        $this->actingAs($this->buyer)->post("/ads/{$this->ad->id}/chat")->assertNotFound();
        $this->assertSame(0, Conversation::count());
    }

    public function test_bad_messages_are_refused(): void
    {
        $this->on();
        $c = $this->start();

        $this->say($this->buyer, $c, '   ')->assertUnprocessable();
        $this->say($this->buyer, $c, str_repeat('a', Message::MAX_LENGTH + 1))->assertUnprocessable();
        $this->say($this->buyer, $c, 'send money first please')->assertUnprocessable()
            ->assertJsonPath('message', 'Your message has words that are not allowed. Please change it.');
        $this->assertSame(0, Message::count());

        // Without JavaScript: back to the form with the message.
        $this->actingAs($this->buyer)->from("/messages/{$c->id}")->post("/messages/{$c->id}", ['body' => 'send money first'])
            ->assertRedirect(url("/messages/{$c->id}"))->assertSessionHasErrors('body');
    }

    public function test_block_stops_both_sides_and_only_the_blocker_can_undo(): void
    {
        $this->on();
        $c = $this->start();
        $this->say($this->buyer, $c, 'Hello');

        $this->actingAs($this->seller)->post("/messages/{$c->id}/block")->assertRedirect();
        $this->say($this->buyer, $c, 'Hello?')->assertUnprocessable()->assertJsonPath('message', 'This chat is blocked. No more messages can be sent.');
        $this->say($this->seller, $c, 'x')->assertUnprocessable();
        $this->actingAs($this->buyer)->getJson("/messages/{$c->id}/since")->assertJsonPath('blocked', true);
        $this->actingAs($this->buyer)->get("/messages/{$c->id}")->assertSee('This chat was blocked.')->assertDontSee('data-chat-form', false);

        $this->actingAs($this->buyer)->post("/messages/{$c->id}/block"); // not theirs to undo
        $this->assertSame($this->seller->id, $c->fresh()?->blocked_by);
        $this->actingAs($this->seller)->post("/messages/{$c->id}/block");
        $this->assertNull($c->fresh()?->blocked_by);
        $this->say($this->buyer, $c, 'Thanks')->assertCreated();
    }

    public function test_chat_with_a_banned_person_stops(): void
    {
        $this->on();
        $c = $this->start();
        $this->buyer->forceFill(['banned_at' => now()])->save();

        $this->say($this->seller, $c, 'Hi')->assertUnprocessable()->assertJsonPath('message', 'This person can no longer receive messages.');
    }

    public function test_guests_get_a_sign_in_link_and_cached_pages_stay_cookie_free(): void
    {
        $this->on();
        $res = $this->get($this->ad->url())->assertOk()->assertSee('Sign in to chat with the seller');
        $this->assertSame([], $res->headers->getCookies());
    }

    public function test_deleting_the_ad_keeps_the_chat(): void
    {
        $this->on();
        $c = $this->start();
        $this->say($this->buyer, $c, 'Hello');

        $this->ad->delete();

        $this->actingAs($this->seller)->get("/messages/{$c->id}")->assertOk()->assertSee('This ad is no longer on the site.')->assertSee('Hello');
    }
}
