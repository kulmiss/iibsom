<?php

namespace Tests\Feature\Admin;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Testing\TestResponse;

/** PR 73: the drug check in chat messages. */
class ChatDrugsTest extends AdminTestCase
{
    private User $buyer;

    private User $seller;

    private Ad $ad;

    private Conversation $chat;

    protected function setUp(): void
    {
        parent::setUp();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        app(FeatureManager::class)->set('chat', true);
        $this->buyer = User::factory()->completed()->create(['name' => 'Hassan Buyer']);
        $this->seller = User::factory()->completed()->create(['name' => 'Amina Seller']);
        $this->ad = Ad::factory()->live()->create([
            'user_id' => $this->seller->id, 'title' => 'Toyota Vitz chat test',
            'category_id' => Category::where('slug', 'cars')->value('id'), 'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);
        $this->actingAs($this->buyer)->post("/ads/{$this->ad->id}/chat")->assertRedirect();
        $this->chat = Conversation::firstOrFail();
    }

    private function say(User $who, string $text, string $prefix = ''): TestResponse
    {
        return $this->actingAs($who)->postJson("{$prefix}/messages/{$this->chat->id}", ['body' => $text]);
    }

    public function test_drug_messages_are_blocked_with_a_kind_note_and_the_sender_is_marked(): void
    {
        $this->say($this->buyer, 'Do you also have fresh khat?')->assertUnprocessable()
            ->assertJsonPath('message', 'Sorry, this message was not sent. Talking about drugs (khat, cannabis, pills and similar) is not allowed on iibsom. Please write about the item only.');
        $this->say($this->buyer, 'Ma haysaa c0caine?', '/so')->assertUnprocessable()
            ->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Waan ka xunnahay, fariintan lama dirin.'));

        $this->assertSame(0, Message::count(), 'nothing is sent or saved');
        $buyer = $this->buyer->fresh();
        $this->assertSame(['drugs', 'cocaine', 2], [$buyer->flag, $buyer->flag_word, $buyer->flag_count]);
        $this->assertNull($buyer->banned_at, 'never banned automatically');
        $this->assertNull($this->seller->fresh()->flag);

        // The person can still write normally.
        $this->say($this->buyer, 'Is the car still available?')->assertCreated();
    }

    public function test_safe_words_pass(): void
    {
        foreach (['Iska qaad hadda', 'Jaad kasta waan haynaa', 'Can we chat tomorrow?', 'I am travelling to Qatar', 'Khatar ma leh', 'Weed killer too?'] as $text) {
            $this->say($this->buyer, $text)->assertCreated();
        }
        $this->assertSame(6, Message::count());
        $this->assertNull($this->buyer->fresh()->flag);
    }

    public function test_the_chat_page_gets_a_quiet_answer_and_without_javascript_the_text_comes_back(): void
    {
        // What chat.js sends: no error status, so the browser console stays clean.
        $this->actingAs($this->buyer)->postJson("/messages/{$this->chat->id}", ['body' => 'Tramadol for sale'], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJsonPath('ok', false)->assertJsonPath('message', fn ($m) => str_contains($m, 'not allowed on iibsom'));

        $this->actingAs($this->buyer)->from("/messages/{$this->chat->id}")->post("/messages/{$this->chat->id}", ['body' => 'qaadka cusub'])
            ->assertRedirect("/messages/{$this->chat->id}")->assertSessionHasErrors('body')->assertSessionHasInput('body', 'qaadka cusub');
        $this->assertSame(0, Message::count());
    }

    public function test_the_word_list_from_settings_and_the_switch(): void
    {
        app(SettingsManager::class)->set('safety.drug_words', ['birad']);
        $this->say($this->buyer, 'birad?')->assertUnprocessable();
        $this->say($this->buyer, 'khat?')->assertCreated(); // no longer on the list

        app(FeatureManager::class)->set('safety.no_drugs', false);
        $this->say($this->buyer, 'birad?')->assertCreated();
    }

    public function test_staff_see_marked_people_and_their_ads_with_the_drugs_filters(): void
    {
        $this->say($this->seller, 'I also sell miraa')->assertUnprocessable();
        $other = User::factory()->completed()->create();

        $this->asStaff('moderator');
        $users = $this->getJson('/admin/api/users?state=drugs')->assertOk()->json('data');
        $this->assertSame([$this->seller->id], array_column($users, 'id'));
        $this->assertStringStartsWith('Drug words in chat: “miraa” (1×, last ', $users[0]['flag']);
        $this->assertNotContains($other->id, array_column($users, 'id'));

        $ads = $this->getJson('/admin/api/ads?flag=drugs')->assertOk()->json('data');
        $this->assertSame([$this->ad->id], array_column($ads, 'id'));
        $this->assertSame('Seller wrote drug words in chat: “miraa”', $ads[0]['flag']);

        // Staff looked and it was nothing: clear the mark.
        $this->postJson("/admin/api/users/{$this->seller->id}/clear-flag")->assertOk()->assertJsonPath('flag', null);
        $this->assertSame([], $this->getJson('/admin/api/users?state=drugs')->json('data'));
        $this->assertDatabaseHas('admin_activities', ['action' => 'user.flag_cleared']);
    }

    public function test_only_staff_who_may_ban_can_clear_the_mark(): void
    {
        $this->say($this->buyer, 'weed?')->assertUnprocessable();
        $this->asStaff('support');
        $this->postJson("/admin/api/users/{$this->buyer->id}/clear-flag")->assertForbidden();
        $this->assertSame('drugs', $this->buyer->fresh()->flag);
    }
}
