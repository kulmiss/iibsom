<?php

namespace Tests\Feature\Admin;

use App\Models\Ad;
use App\Models\AdBoost;
use App\Models\Category;
use App\Models\City;
use App\Models\Payment;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Money\Boosts;
use App\Support\Money\Ledger;
use App\Support\Settings\SettingsManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use InvalidArgumentException;

class BoostsTest extends AdminTestCase
{
    private User $seller;

    private Ad $ad;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->seller = User::factory()->completed()->create();
        $this->ad = $this->car('Toyota Vitz to boost', now()->subDays(5));
    }

    private function car(string $title, $publishedAt): Ad
    {
        return Ad::factory()->live()->create([
            'user_id' => $this->seller->id, 'title' => $title, 'published_at' => $publishedAt,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);
    }

    private function on(): void
    {
        app(FeatureManager::class)->set('boosts', true);
    }

    private function pay(array $extra = [], ?Ad $ad = null): TestResponse
    {
        $ad ??= $this->ad;

        return $this->actingAs($this->seller)->post("/account/ads/{$ad->id}/boost", $extra + [
            'package' => 'vip',
            'method' => 'evc_plus',
            'reference' => 'TX12345678',
            'receipt' => UploadedFile::fake()->image('sms.jpg', 700, 1200),
        ]);
    }

    public function test_off_by_default(): void
    {
        $this->assertFalse(feature('boosts'));
        $this->actingAs($this->seller)->get("/account/ads/{$this->ad->id}/boost")->assertNotFound();
        $this->pay()->assertNotFound();
        $this->actingAs($this->seller)->get('/account/ads')->assertDontSee('/boost"', false);
        $this->asStaff();
        $this->getJson('/admin/api/payments')->assertNotFound();
        // A boost bought earlier does nothing while the switch is OFF.
        $this->ad->forceFill(['boost_level' => 3, 'boosted_until' => now()->addDay()])->save();
        $this->assertSame(0, $this->ad->fresh()?->activeBoost());
    }

    public function test_seller_pays_in_test_mode_and_the_price_comes_from_the_server(): void
    {
        $this->on();
        $this->actingAs($this->seller)->get("/account/ads/{$this->ad->id}/boost")->assertOk()
            ->assertSee('TEST MODE: payments are being tested. Do not send real money.')
            ->assertSeeInOrder(['Top', '$3.00', 'VIP', '$8.00', 'Diamond', '$15.00'])
            ->assertSee('EVC Plus')->assertSee('eDahab');
        $this->actingAs($this->seller)->get("/so/account/ads/{$this->ad->id}/boost")->assertOk()->assertSee('HABKA TIJAABADA');

        $this->pay(['amount_cents' => 1, 'reference' => 'tx-abc.99'])->assertRedirect(url('/account/payments'));

        $p = Payment::firstOrFail();
        $this->assertSame(800, $p->amount_cents);
        $this->assertSame('TX-ABC.99', $p->reference);
        $this->assertTrue($p->is_test);
        $this->assertSame('pending', $p->status);
        $this->assertStringNotContainsString("\xFF\xD8\xFF", (string) Storage::disk('local')->get((string) $p->receipt_path), 'Receipt stored encrypted.');
        $this->actingAs($this->seller)->get('/account/payments')->assertOk()->assertSee('VIP · $8.00')->assertSee('Being checked')->assertSee('Test');
        // One payment at a time per ad.
        $this->actingAs($this->seller)->get("/account/ads/{$this->ad->id}/boost")->assertSee('We are checking a payment for this ad.');
    }

    public function test_bad_payments_are_refused(): void
    {
        $this->on();
        $other = Ad::factory()->live()->create(['category_id' => $this->ad->category_id, 'city_id' => $this->ad->city_id]);

        $this->pay(['package' => 'gold'])->assertSessionHasErrors('package');
        $this->pay(['method' => 'paypal'])->assertSessionHasErrors('method');
        $this->pay(['reference' => 'TX 1; DROP'])->assertSessionHasErrors('reference');
        $this->pay(['receipt' => UploadedFile::fake()->create('r.pdf', 20, 'application/pdf')])->assertSessionHasErrors('receipt');
        $this->pay(ad: $other)->assertNotFound(); // not my ad
        $this->assertSame(0, Payment::count());
    }

    public function test_one_transaction_id_pays_only_once(): void
    {
        $this->on();
        $second = $this->car('Second car', now()->subDay());

        $this->pay()->assertRedirect();
        $this->pay(['reference' => 'tx12345678'], $second)->assertSessionHasErrors(['reference' => 'This transaction ID was already used. Check the SMS and try again.']);
        $this->assertSame(1, Payment::count());
        // The same ID with another method is a different transaction.
        $this->pay(['method' => 'zaad'], $second)->assertRedirect(url('/account/payments'));
    }

    public function test_approve_writes_balanced_books_and_starts_the_boost_once(): void
    {
        $this->on();
        $this->pay();
        $p = Payment::firstOrFail();

        $staff = $this->asStaff();
        $this->getJson('/admin/api/payments')->assertOk()->assertJsonPath('data.0.amount', '$8.00')->assertJsonPath('data.0.is_test', true)->assertJsonPath('test_mode', true);
        $this->get("/admin/api/payments/{$p->id}/receipt")->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $this->postJson("/admin/api/payments/{$p->id}/approve")->assertOk();
        $this->postJson("/admin/api/payments/{$p->id}/approve")->assertStatus(409);
        $this->postJson("/admin/api/payments/{$p->id}/reject", ['reason' => 'late'])->assertStatus(409);

        $this->assertSame('approved', $p->fresh()?->status);
        $this->assertSame($staff->id, $p->fresh()?->reviewed_by);
        $this->assertSame(2, DB::table('ledger_entries')->count());
        $this->assertSame(['cash:evc_plus' => 800, 'revenue:boosts' => -800], Ledger::balances(true));
        $this->assertSame([], Ledger::balances(false), 'Test money never reaches the real books.');
        $this->assertSame([], Ledger::unbalanced());
        $this->artisan('iibsom:ledger:check')->expectsOutputToContain('Ledger OK: 1 approved payment(s)')->assertSuccessful();
        $this->getJson('/admin/api/payments/summary')->assertJsonPath('test.cash:evc_plus', 800)->assertJsonPath('unbalanced', 0);

        $ad = $this->ad->fresh();
        $this->assertSame(2, $ad?->activeBoost());
        $this->assertEqualsWithDelta(now()->addDays(14)->getTimestamp(), $ad?->boosted_until?->getTimestamp(), 5);
        $this->assertSame(1, AdBoost::count());
    }

    public function test_boosted_ads_show_first_with_a_label_and_extend(): void
    {
        $this->on();
        $this->car('Newest normal car', now());
        $this->pay(['package' => 'top']);
        $this->asStaff();
        $this->postJson('/admin/api/payments/'.Payment::firstOrFail()->id.'/approve')->assertOk();
        auth()->logout();

        $this->get('/cars')->assertOk()->assertSeeInOrder(['Top', 'Toyota Vitz to boost', 'Newest normal car']);
        $this->get('/cars?sort=cheapest')->assertOk(); // other orders ignore boosts

        // A second boost adds its days to the end and keeps the higher level.
        $firstEnd = $this->ad->fresh()?->boosted_until;
        $this->pay(['package' => 'diamond', 'reference' => 'TX99999999']);
        $this->asStaff();
        $this->postJson('/admin/api/payments/'.Payment::latest('id')->firstOrFail()->id.'/approve')->assertOk();
        $ad = $this->ad->fresh();
        $this->assertSame(3, $ad?->boost_level);
        $this->assertSame($firstEnd?->copy()->addDays(30)->getTimestamp(), $ad?->boosted_until?->getTimestamp());
    }

    public function test_reject_writes_nothing_in_the_books(): void
    {
        $this->on();
        $this->pay();
        $p = Payment::firstOrFail();

        $this->asStaff();
        $this->postJson("/admin/api/payments/{$p->id}/reject", ['reason' => ''])->assertUnprocessable();
        $this->postJson("/admin/api/payments/{$p->id}/reject", ['reason' => 'Money did not arrive'])->assertOk();

        $this->assertSame(0, DB::table('ledger_entries')->count());
        $this->assertSame(0, $this->ad->fresh()?->activeBoost());
        $this->actingAs($this->seller)->get('/account/payments')->assertSee('Not approved: Money did not arrive');
    }

    public function test_ended_boosts_go_back_to_normal(): void
    {
        $this->on();
        $this->ad->forceFill(['boost_level' => 2, 'boosted_until' => now()->subMinute()])->save();
        $this->assertSame(0, $this->ad->fresh()?->activeBoost(), 'The label goes at once, before the job runs.');

        $this->assertSame(1, Boosts::endExpired());
        $this->assertSame(0, $this->ad->fresh()?->boost_level);
    }

    public function test_test_mode_off_needs_a_merchant_number_and_marks_real_money(): void
    {
        $this->on();
        $this->asStaff();
        $base = ['ads_expiry_days' => 60, 'ads_max_per_day' => 5, 'ads_max_photos' => 8, 'languages' => ['en']];

        $this->putJson('/admin/api/settings/general', $base + ['payments_test_mode' => false])
            ->assertUnprocessable()->assertJsonPath('errors.payments_test_mode.0', 'Add at least one merchant number before turning test mode off.');
        $this->putJson('/admin/api/settings/general', $base + ['payment_numbers' => ['evc_plus' => 'abc']])->assertUnprocessable();
        $this->putJson('/admin/api/settings/general', $base + ['payments_test_mode' => false, 'payment_numbers' => ['evc_plus' => '+252 61 000 0000']])->assertOk()
            ->assertJsonPath('payments_test_mode', false)->assertJsonPath('payment_numbers.evc_plus', '+252 61 000 0000');
        auth()->logout();

        // Only methods with a number are offered now, and payments are real.
        $this->actingAs($this->seller)->get("/account/ads/{$this->ad->id}/boost")->assertOk()
            ->assertDontSee('TEST MODE')->assertSee('+252 61 000 0000')->assertDontSee('Zaad');
        $this->pay(['method' => 'zaad'])->assertSessionHasErrors('method');
        $this->pay()->assertRedirect();
        $this->assertFalse(Payment::firstOrFail()->is_test);
    }

    public function test_only_the_super_admin_sees_payments(): void
    {
        $this->on();
        $this->pay();
        $p = Payment::firstOrFail();

        foreach (['moderator', 'support'] as $role) {
            $this->asStaff($role);
            $this->getJson('/admin/api/payments')->assertForbidden();
            $this->get("/admin/api/payments/{$p->id}/receipt")->assertForbidden();
            $this->postJson("/admin/api/payments/{$p->id}/approve")->assertForbidden();
        }
        $this->assertSame('pending', $p->fresh()?->status);
    }

    public function test_ledger_refuses_unbalanced_or_bad_lines(): void
    {
        foreach ([
            [['account' => 'cash:evc_plus', 'debit' => 100]],
            [['account' => 'cash:evc_plus', 'debit' => 100], ['account' => 'revenue:boosts', 'credit' => 99]],
            [['account' => 'cash:evc_plus', 'debit' => -100], ['account' => 'revenue:boosts', 'credit' => -100]],
            [['account' => 'cash:evc_plus', 'debit' => 100, 'credit' => 100], ['account' => 'revenue:boosts', 'credit' => 0]],
            [['account' => 'Cash EVC', 'debit' => 100], ['account' => 'revenue:boosts', 'credit' => 100]],
        ] as $lines) {
            try {
                Ledger::post($lines, true);
                $this->fail('Ledger accepted bad lines: '.json_encode($lines));
            } catch (InvalidArgumentException) {
                // expected
            }
        }
        $this->assertSame(0, DB::table('ledger_entries')->count());
    }

    public function test_ledger_check_finds_problems(): void
    {
        DB::table('ledger_entries')->insert([
            'transaction_id' => 'broken-1', 'account' => 'cash:zaad', 'debit_cents' => 500, 'credit_cents' => 0, 'is_test' => false, 'created_at' => now(),
        ]);

        $this->artisan('iibsom:ledger:check')->expectsOutputToContain('debits and credits are not equal')->assertFailed();
    }

    public function test_money_is_shown_from_whole_cents(): void
    {
        $this->assertSame('$0.05', Payment::money(5));
        $this->assertSame('$8.00', Payment::money(800));
        $this->assertSame('$1234.56', Payment::money(123456));
    }
}
