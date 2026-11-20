<?php

namespace Tests\Feature\Admin;

use App\Models\Ad;
use App\Models\AdminActivity;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;

/** PR 108: "Phone confirmed" tick, set by staff after a call or WhatsApp. No SMS. */
class PhoneConfirmedTest extends AdminTestCase
{
    private User $seller;

    private Ad $ad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->completed()->create(['name' => 'Hodan Seller', 'phone' => '+252615123456']);
        $this->ad = Ad::factory()->live()->create([
            'user_id' => $this->seller->id,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);
    }

    private function confirm(): void
    {
        $this->asStaff('moderator');
        $this->postJson("/admin/api/users/{$this->seller->id}/phone-confirmed")->assertOk();
        auth()->logout();
    }

    public function test_staff_tick_it_logged_and_it_shows_on_ad_and_seller_pages_with_help(): void
    {
        $this->get($this->ad->url())->assertOk()->assertDontSee('data-phone-tick', false);

        $mod = $this->asStaff('moderator');
        $row = $this->postJson("/admin/api/users/{$this->seller->id}/phone-confirmed")->assertOk()->json();
        $this->assertNotNull($row['phone_confirmed']['at']);
        $this->assertSame($mod->name, $row['phone_confirmed']['by']);
        $this->seller->refresh();
        $this->assertSame($mod->id, $this->seller->phone_confirmed_by);
        $log = AdminActivity::where('action', 'user.phone_confirmed')->firstOrFail();
        $this->assertSame($mod->id, $log->user_id);
        $this->postJson("/admin/api/users/{$this->seller->id}/phone-confirmed")->assertStatus(409);
        auth()->logout();

        $this->get($this->ad->url())->assertOk()->assertSee('data-phone-tick', false)->assertSee('Phone confirmed')->assertSee('What does this mean?');
        $this->get("/seller/{$this->seller->id}")->assertOk()->assertSee('data-phone-tick', false);

        // Remove it again (logged too).
        $this->asStaff('moderator');
        $this->deleteJson("/admin/api/users/{$this->seller->id}/phone-confirmed")->assertOk();
        $this->assertNull($this->seller->fresh()->phone_confirmed_at);
        $this->assertSame(1, AdminActivity::where('action', 'user.phone_unconfirmed')->count());
    }

    public function test_only_staff_who_check_ids_may_tick_it_and_never_the_seller(): void
    {
        $this->actingAs($this->seller)->postJson("/admin/api/users/{$this->seller->id}/phone-confirmed")->assertUnauthorized();
        auth()->logout();
        $this->asStaff('support');
        $this->postJson("/admin/api/users/{$this->seller->id}/phone-confirmed")->assertForbidden();
        $this->assertNull($this->seller->fresh()->phone_confirmed_at);

        // There is no customer route that sets it.
        $this->actingAs($this->seller)->post('/account/phone/code')->assertRedirect();
        $this->assertNull($this->seller->fresh()->phone_confirmed_at);
    }

    public function test_changing_the_number_removes_the_tick_and_the_code(): void
    {
        $this->confirm();
        $this->seller->refresh();
        $this->assertNotNull($this->seller->phone_confirmed_at);

        $this->actingAs($this->seller)->post('/account/profile', [
            'name' => 'Hodan Seller', 'city_id' => City::where('slug', 'mogadishu')->value('id'), 'phone' => '0617654321', 'phone_has_whatsapp' => '1',
        ])->assertRedirect();

        $this->seller->refresh();
        $this->assertSame('+252617654321', $this->seller->phone);
        $this->assertNull($this->seller->phone_confirmed_at);
        $this->assertNull($this->seller->phone_confirmed_by);
        $this->get($this->ad->url())->assertOk()->assertDontSee('data-phone-tick', false);

        // Saving the profile without changing the number keeps it.
        $this->confirm();
        $this->actingAs($this->seller)->post('/account/profile', [
            'name' => 'Hodan S.', 'city_id' => City::where('slug', 'mogadishu')->value('id'), 'phone' => '0617654321', 'phone_has_whatsapp' => '1',
        ])->assertRedirect();
        $this->assertNotNull($this->seller->fresh()->phone_confirmed_at);
    }

    public function test_whatsapp_code_helper(): void
    {
        $this->actingAs($this->seller)->get('/account/phone')->assertOk()->assertSee('data-phone-get-code', false)->assertDontSee('data-phone-code', false);

        $this->post('/account/phone/code')->assertRedirect(url('/account/phone'));
        $code = (string) $this->seller->fresh()->phone_confirm_code;
        $this->assertMatchesRegularExpression('/^IIB-[A-HJ-NP-Z2-9]{5}$/', $code);

        // No WhatsApp number set: no button, staff call instead.
        $this->get('/account/phone')->assertOk()->assertSee($code)->assertDontSee('data-phone-whatsapp', false)->assertSee('Our team will call you');

        app(SettingsManager::class)->set('contact.confirm_whatsapp', '+252 61 000 1111');
        $html = (string) $this->get('/account/phone')->assertOk()->getContent();
        $this->assertStringContainsString('href="https://wa.me/252610001111?text=', $html);
        $this->assertStringContainsString(rawurlencode($code), $html);

        // Asking again keeps the same code while it is fresh; a new one after 7 days.
        $this->post('/account/phone/code');
        $this->assertSame($code, $this->seller->fresh()->phone_confirm_code);
        $this->travel(8)->days();
        $this->post('/account/phone/code');
        $this->assertNotSame($code, $this->seller->fresh()->phone_confirm_code);

        // Staff see the code in the user panel and can find the person by it.
        $fresh = (string) $this->seller->fresh()->phone_confirm_code;
        auth()->logout();
        $this->asStaff('moderator');
        $this->assertSame($fresh, $this->getJson("/admin/api/users/{$this->seller->id}")->json('phone_code.code'));
        $found = $this->getJson('/admin/api/search?q='.strtolower(substr($fresh, 4)))->assertOk()->json('groups.users');
        $this->assertSame($this->seller->id, $found[0]['id']);

        // Once confirmed, the code is gone.
        $this->postJson("/admin/api/users/{$this->seller->id}/phone-confirmed")->assertOk();
        $this->assertNull($this->seller->fresh()->phone_confirm_code);
    }

    public function test_switch_off_hides_everything(): void
    {
        $this->confirm();
        app(FeatureManager::class)->set('sellers.phone_confirmed', false);

        $this->get($this->ad->url())->assertOk()->assertDontSee('data-phone-tick', false);
        $this->actingAs($this->seller)->get('/account/phone')->assertNotFound();
        $this->actingAs($this->seller)->get('/account')->assertOk()->assertDontSee('data-phone-menu', false);
        auth()->logout();
        $this->asStaff('moderator');
        $this->postJson("/admin/api/users/{$this->seller->id}/phone-confirmed")->assertNotFound();
        $this->assertNull($this->getJson("/admin/api/users/{$this->seller->id}")->json('phone_confirmed'));
    }

    public function test_whatsapp_number_setting_is_checked(): void
    {
        $this->asStaff();
        $values = $this->getJson('/admin/api/settings/general')->assertOk()->json();
        $this->putJson('/admin/api/settings/general', ['confirm_whatsapp' => '<script>'] + $values)->assertUnprocessable();
        $this->putJson('/admin/api/settings/general', ['confirm_whatsapp' => '+252 61 000 1111'] + $values)->assertOk();
        $this->assertSame('+252 61 000 1111', setting('contact.confirm_whatsapp'));
    }
}
