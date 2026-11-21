<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivity;
use App\Support\Features\Dependencies;
use App\Support\Features\FeatureManager;
use App\Support\Features\LaunchSetup;

/** PR 106: launch defaults, the "Launch setup" button, and "Needs Chat (OFF)". */
class LaunchSetupTest extends AdminTestCase
{
    private function features(): FeatureManager
    {
        return app(FeatureManager::class);
    }

    public function test_free_features_are_on_by_default_for_launch_and_chat_stays_off(): void
    {
        foreach (['favourites', 'saved_searches', 'ad_stats', 'verified_badge', 'social.autopost', 'diaspora', 'login.email'] as $key) {
            $this->assertTrue($this->features()->enabled($key), $key);
        }
        foreach (['chat', 'seller_ratings', 'login.phone', 'boosts', 'shops'] as $key) {
            $this->assertFalse($this->features()->enabled($key), $key);
        }
    }

    public function test_preview_lists_only_what_would_change_and_changes_nothing(): void
    {
        $this->asStaff();
        $this->features()->set('chat', true);
        $this->features()->set('favourites', false);
        $this->features()->set('contact.whatsapp', false); // not in the setup: never touched

        $changes = collect($this->getJson('/admin/api/features/launch')->assertOk()->json('changes'))->keyBy('key');

        $this->assertTrue($changes['chat']['from']);
        $this->assertFalse($changes['chat']['to']);
        $this->assertFalse($changes['favourites']['from']);
        $this->assertTrue($changes['favourites']['to']);
        $this->assertNotEmpty($changes['favourites']['why']);
        $this->assertArrayNotHasKey('contact.whatsapp', $changes->all());
        $this->assertArrayNotHasKey('saved_searches', $changes->all()); // already right

        // Looking changes nothing.
        $this->assertTrue($this->features()->enabled('chat'));
        $this->assertFalse($this->features()->enabled('favourites'));
    }

    public function test_apply_changes_exactly_the_shown_switches_and_logs_it(): void
    {
        $this->asStaff();
        $this->features()->set('chat', true);
        $this->features()->set('favourites', false);
        $this->features()->set('contact.whatsapp', false);
        $keys = collect(LaunchSetup::changes($this->features()))->pluck('key')->all();

        $this->postJson('/admin/api/features/launch', ['keys' => $keys])->assertOk()->assertJson(['changed' => count($keys)]);

        $this->assertFalse($this->features()->enabled('chat'));
        $this->assertTrue($this->features()->enabled('favourites'));
        $this->assertFalse($this->features()->enabled('contact.whatsapp')); // your own choice stays
        $this->assertSame([], LaunchSetup::changes($this->features()));

        $log = AdminActivity::where('action', 'feature.launch_setup')->latest('id')->firstOrFail();
        $this->assertStringContainsString('“In-website chat” OFF', $log->summary);
        $this->assertStringContainsString('“Favourites” ON', $log->summary);

        // Second click: nothing to change, still logged.
        $this->postJson('/admin/api/features/launch', ['keys' => []])->assertOk()->assertJson(['changed' => 0]);
    }

    public function test_apply_refuses_when_the_list_is_out_of_date(): void
    {
        $this->asStaff();
        $this->features()->set('chat', true);
        $keys = collect(LaunchSetup::changes($this->features()))->pluck('key')->all();
        $this->features()->set('boosts', true); // changed in another tab meanwhile

        $this->postJson('/admin/api/features/launch', ['keys' => $keys])->assertStatus(409);
        $this->assertTrue($this->features()->enabled('chat'));
        $this->assertTrue($this->features()->enabled('boosts'));
        $this->postJson('/admin/api/features/launch', ['keys' => 'chat'])->assertUnprocessable();
    }

    public function test_only_staff_who_manage_switches(): void
    {
        $this->asStaff('moderator');
        $this->getJson('/admin/api/features/launch')->assertForbidden();
        $this->postJson('/admin/api/features/launch', ['keys' => []])->assertForbidden();
    }

    public function test_every_preset_switch_exists(): void
    {
        foreach (array_keys(LaunchSetup::PRESET) as $key) {
            $this->assertTrue($this->features()->exists($key), $key);
        }
        foreach (Dependencies::NEEDS as $key => $needs) {
            $this->assertTrue($this->features()->exists($key), $key);
            foreach ($needs as $need) {
                $this->assertTrue($this->features()->exists($need), $need);
            }
        }
    }

    public function test_switches_waiting_for_chat_say_so(): void
    {
        $this->asStaff();
        $this->features()->set('chat', false);
        $this->features()->set('seller_ratings', true);

        $items = collect($this->getJson('/admin/api/features')->assertOk()->json('groups'))->pluck('items')->flatten(1)->keyBy('key');

        $this->assertSame(['key' => 'chat', 'name' => 'Chat'], $items['chat.offers']['waiting_for']);
        $this->assertSame(['key' => 'chat', 'name' => 'Chat'], $items['ads.card_stars']['waiting_for']); // through Seller ratings
        $this->assertNull($items['chat']['waiting_for']);
        $this->assertNull($items['contact.whatsapp']['waiting_for']);

        $this->features()->set('seller_ratings', false);
        $items = collect($this->getJson('/admin/api/features')->json('groups'))->pluck('items')->flatten(1)->keyBy('key');
        $this->assertSame('seller_ratings', $items['ads.card_stars']['waiting_for']['key']);

        $this->features()->set('chat', true);
        $this->features()->set('seller_ratings', true);
        $items = collect($this->getJson('/admin/api/features')->json('groups'))->pluck('items')->flatten(1)->keyBy('key');
        $this->assertNull($items['chat.offers']['waiting_for']);
        $this->assertNull($items['ads.card_stars']['waiting_for']);
    }
}
