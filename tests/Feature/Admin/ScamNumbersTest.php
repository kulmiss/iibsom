<?php

namespace Tests\Feature\Admin;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\AdminActivity;
use App\Models\Category;
use App\Models\City;
use App\Models\ScamAppeal;
use App\Models\ScamNumber;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Safety\ScamNumbers;
use App\Support\Watchdog\Checks\ScamNumbersCheck;
use App\Support\Watchdog\Signals;
use App\Support\Watchdog\Watchdog;
use Illuminate\Testing\TestResponse;

/** PR 109: scam number warning list. */
class ScamNumbersTest extends AdminTestCase
{
    private const NUMBER = '+252615123456';

    private User $seller;

    private Ad $ad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->completed()->create(['name' => 'Secret Name', 'phone' => self::NUMBER]);
        $this->ad = $this->ad(['user_id' => $this->seller->id, 'title' => 'Toyota Vitz clean']);
    }

    /** @param  array<string, mixed>  $attrs */
    private function ad(array $attrs = []): Ad
    {
        return Ad::factory()->live()->create($attrs + [
            'user_id' => User::factory()->completed()->create()->id,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);
    }

    private function mark(string $phone = '061 512 3456'): TestResponse
    {
        $this->asStaff('moderator');

        return $this->postJson('/admin/api/scam-numbers', ['phone' => $phone, 'reason' => 'Took deposits, never delivered', 'proof' => 'Reports #12, #15; EVC receipts']);
    }

    public function test_only_staff_who_can_ban_mark_numbers_and_proof_is_required(): void
    {
        $this->postJson('/admin/api/scam-numbers', ['phone' => self::NUMBER, 'reason' => 'x', 'proof' => 'y'])->assertUnauthorized();
        $this->asStaff('support');
        $this->postJson('/admin/api/scam-numbers', ['phone' => self::NUMBER, 'reason' => 'Scam', 'proof' => 'Reports'])->assertForbidden();

        $this->asStaff('moderator');
        $this->postJson('/admin/api/scam-numbers', ['phone' => self::NUMBER, 'reason' => 'Scam'])->assertUnprocessable()->assertJsonValidationErrors('proof');
        $this->postJson('/admin/api/scam-numbers', ['phone' => '12345', 'reason' => 'Scam', 'proof' => 'Reports'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->assertSame(0, ScamNumber::count());
    }

    public function test_marking_sends_live_ads_to_review_and_logs_it(): void
    {
        $other = $this->ad(['title' => 'Phone for sale', 'description' => 'Very good phone, call me on 0615 123 456 any time.']);
        $clean = $this->ad(['title' => 'Honest ad', 'description' => 'Call 0617 000 111 for this nice phone in good shape.']);

        $res = $this->mark()->assertCreated()->assertJson(['phone' => self::NUMBER, 'ads_to_review' => 2]);
        $this->assertSame(AdStatus::Pending, $this->ad->fresh()->status);
        $this->assertSame(ScamNumbers::FLAG, $this->ad->fresh()->flag);
        $this->assertSame(AdStatus::Pending, $other->fresh()->status); // the number written in the text
        $this->assertSame(AdStatus::Live, $clean->fresh()->status);
        $this->assertStringContainsString(self::NUMBER, (string) AdminActivity::where('action', 'scam.marked')->value('summary'));

        // Twice: refused.
        $this->postJson('/admin/api/scam-numbers', ['phone' => self::NUMBER, 'reason' => 'Again', 'proof' => 'More'])->assertStatus(409);
        $this->assertSame(1, $res->json('id') ? ScamNumber::count() : 0);

        // It cannot be approved while listed.
        $this->postJson("/admin/api/ads/{$this->ad->id}/approve")->assertUnprocessable();
        $this->assertSame(AdStatus::Pending, $this->ad->fresh()->status);
    }

    public function test_a_listed_number_cannot_post_renew_or_go_live_and_the_watchdog_hears(): void
    {
        $this->mark()->assertCreated();
        auth()->logout();
        $cars = Category::where('slug', 'goats-sheep')->firstOrFail(); // no required details, no New/Used

        $this->actingAs($this->seller)->post('/post/'.$cars->slug, [
            'title' => 'Healthy goats', 'description' => 'Ten healthy goats for sale, call me today.', 'price' => 500,
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ])->assertSessionHasErrors(['title' => __('ads.safety.scam_number')]);

        // Another seller writing the listed number in the text is stopped too.
        $other = User::factory()->completed()->create();
        $this->actingAs($other)->post('/post/'.$cars->slug, [
            'title' => 'Sheep for Eid', 'description' => 'Call my friend on +252 61 512 3456 for these sheep.', 'price' => 400,
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ])->assertSessionHasErrors('title');
        $this->assertSame(0, Ad::where('title', 'Sheep for Eid')->count());

        // A clean ad from that other seller still posts (the check is not too wide).
        $this->actingAs($other)->post('/post/'.$cars->slug, [
            'title' => 'Sheep for sale', 'description' => 'Call me on 0617 000 111 for these sheep.', 'price' => 400,
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ])->assertSessionHasNoErrors();

        // Renewing an ended ad is refused too.
        $ended = $this->ad(['user_id' => $this->seller->id, 'status' => AdStatus::Expired]);
        $this->actingAs($this->seller)->post("/account/ads/{$ended->id}/renew")->assertSessionHas('error', __('ads.safety.scam_number'));
        $this->assertSame(AdStatus::Expired, $ended->fresh()->status);

        // Even code that sets an ad live directly cannot: it stays waiting.
        $ad = $this->ad(['user_id' => $this->seller->id, 'status' => AdStatus::Pending]);
        $ad->forceFill(['status' => AdStatus::Live])->save();
        $this->assertSame(AdStatus::Pending, $ad->fresh()->status);

        $finding = (new ScamNumbersCheck)->run(app(Watchdog::class), 1);
        $this->assertCount(1, $finding);
        $this->assertStringNotContainsString(self::NUMBER, json_encode($finding) ?: '');
        $this->assertStringNotContainsString('Secret Name', json_encode($finding) ?: '');
        $this->assertGreaterThanOrEqual(2, Signals::recent('scam_post_try', 60));
    }

    public function test_buyers_see_a_warning_when_they_search_or_check_the_number_never_a_name(): void
    {
        $this->mark()->assertCreated();
        auth()->logout();

        $this->get('/search?q=0615123456')->assertOk()->assertSee('data-scam-warning', false)->assertSee('reported for scams')->assertDontSee('Secret Name');
        $this->get('/search?q=toyota')->assertOk()->assertDontSee('data-scam-warning', false);

        $this->get('/check-number?phone=61+512+3456')->assertOk()->assertSee('data-scam-warning', false)->assertDontSee('Secret Name')->assertDontSee('EVC receipts');
        $this->get('/check-number?phone=0617000111')->assertOk()->assertSee('data-scam-clear', false)->assertDontSee('data-scam-warning', false);
        $this->get('/check-number?phone=hello')->assertOk()->assertSee('This is not a Somali mobile number');
        $this->get('/check-number')->assertOk()->assertSee('data-scam-form', false)->assertSee('noindex', false);
        $this->get('/')->assertOk()->assertSee(url('/check-number'), false); // footer link
    }

    public function test_appeal_staff_decide_and_it_is_logged(): void
    {
        $this->mark()->assertCreated();
        auth()->logout();

        $this->post('/check-number/appeal', ['phone' => self::NUMBER, 'message' => 'short'])->assertSessionHasErrors('message');
        $this->post('/check-number/appeal', ['phone' => self::NUMBER, 'message' => 'This is my number and I never cheated anyone.', 'contact' => 'me@example.com'])
            ->assertRedirect()->assertSessionHas('status', __('scam.appeal_sent'));
        // A second one while waiting is not added.
        $this->post('/check-number/appeal', ['phone' => self::NUMBER, 'message' => 'Please, please check my number again.']);
        $this->assertSame(1, ScamAppeal::count());
        $this->get('/check-number?phone='.urlencode(self::NUMBER))->assertSee(__('scam.appeal_waiting'));
        // A number that is not listed: nothing is stored.
        $this->post('/check-number/appeal', ['phone' => '0617000111', 'message' => 'This is not even listed, just testing.']);
        $this->assertSame(1, ScamAppeal::count());

        $appeal = ScamAppeal::firstOrFail();
        $this->asStaff('moderator');
        $this->assertSame('me@example.com', $this->getJson('/admin/api/scam-appeals')->assertOk()->json('data.0.contact'));
        $this->postJson("/admin/api/scam-appeals/{$appeal->id}/decide", ['accept' => false, 'note' => 'Receipts are clear'])->assertOk();
        $this->assertTrue(ScamNumbers::listed(self::NUMBER));
        $this->postJson("/admin/api/scam-appeals/{$appeal->id}/decide", ['accept' => true, 'note' => 'x again'])->assertStatus(409);

        // A new appeal, accepted: the number comes off the list.
        auth()->logout();
        $this->post('/check-number/appeal', ['phone' => self::NUMBER, 'message' => 'I paid the buyer back, here is the receipt number 999.']);
        $second = ScamAppeal::latest('id')->firstOrFail();
        $this->asStaff('moderator');
        $this->postJson("/admin/api/scam-appeals/{$second->id}/decide", ['accept' => true, 'note' => 'Buyer confirmed the refund'])->assertOk();
        $this->assertFalse(ScamNumbers::listed(self::NUMBER));
        $this->assertSame(1, AdminActivity::where('action', 'scam.appeal_refused')->count());
        $this->assertSame(1, AdminActivity::where('action', 'scam.appeal_accepted')->count());

        // The contact is deleted 30 days after the decision.
        ScamAppeal::pruneContacts();
        $this->assertSame('me@example.com', $appeal->fresh()->contact); // not yet
        $this->travel(ScamAppeal::KEEP_CONTACT_DAYS + 1)->days();
        ScamAppeal::pruneContacts();
        $this->assertNull($appeal->fresh()->contact);
    }

    public function test_take_off_the_list_and_switch_off(): void
    {
        $id = $this->mark()->assertCreated()->json('id');
        $this->postJson("/admin/api/scam-numbers/{$id}/remove", ['reason' => 'Marked by mistake'])->assertOk();
        $this->assertFalse(ScamNumbers::listed(self::NUMBER));
        $this->assertSame(1, AdminActivity::where('action', 'scam.removed')->count());
        // Marking again works (history kept on the same row).
        $this->mark()->assertCreated();
        $this->assertSame(1, ScamNumber::count());

        app(FeatureManager::class)->set('safety.scam_numbers', false);
        $this->assertFalse(ScamNumbers::listed(self::NUMBER));
        $this->getJson('/admin/api/scam-numbers')->assertNotFound();
        auth()->logout();
        $this->get('/check-number')->assertNotFound();
        $this->get('/search?q=0615123456')->assertOk()->assertDontSee('data-scam-warning', false);
    }

    public function test_numbers_inside_text_are_found_in_many_forms(): void
    {
        foreach (['call 0615123456', 'call +252 61 512 3456', 'tel: 615-123-456', '00252615123456 now'] as $text) {
            $this->assertSame([self::NUMBER], ScamNumbers::numbersIn($text), $text);
        }
        $this->assertSame([], ScamNumbers::numbersIn('price 6500 dollars, year 2012, 150000 km'));
    }
}
