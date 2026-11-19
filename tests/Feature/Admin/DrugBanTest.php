<?php

namespace Tests\Feature\Admin;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\Report;
use App\Models\User;
use App\Support\Ads\AdCounters;
use App\Support\Features\FeatureManager;
use App\Support\Safety\GuestReports;
use App\Support\Safety\SpamGuard;
use App\Support\Settings\SettingsManager;

/** PR 72: no khat and no drugs on iibsom. */
class DrugBanTest extends AdminTestCase
{
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->seller = User::factory()->completed()->create(['city_id' => City::where('slug', 'mogadishu')->value('id')]);
    }

    private function postCar(array $overrides = [], string $prefix = '')
    {
        return $this->actingAs($this->seller)->post($prefix.'/post/cars', array_merge([
            'title' => 'Toyota Vitz 2012 automatic',
            'description' => 'Very clean car, new tyres, AC works well. No accidents.',
            'price' => 6500,
            'condition' => 'used',
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
            'fields' => ['brand' => 'toyota', 'model' => 'Vitz', 'year' => 2012, 'mileage' => 98000, 'fuel' => 'petrol', 'transmission' => 'automatic'],
        ], $overrides));
    }

    private function live(string $title, string $description = 'A normal ad with a normal description.'): Ad
    {
        return Ad::factory()->live()->create([
            'user_id' => $this->seller->id, 'title' => $title, 'description' => $description,
            'category_id' => Category::where('slug', 'cars')->value('id'), 'city_id' => City::where('slug', 'mogadishu')->value('id'),
            'fields' => ['brand' => 'toyota', 'model' => 'Vitz', 'year' => 2012, 'mileage' => 98000, 'fuel' => 'petrol', 'transmission' => 'automatic'],
        ]);
    }

    // ---- The word check ----

    public function test_drug_words_in_english_somali_and_common_spellings(): void
    {
        $caught = [
            'Fresh khat every morning' => 'khat', 'QAT for sale' => 'qat', 'Miraa from Meru' => 'miraa', 'mirraa cheap' => 'mirraa',
            'Qaadka ugu fiican Hargeysa' => 'qaadka', 'Qaad cusub maanta' => 'qaad cusub', 'jaad fresh ah' => 'jaad fresh',
            'Weed for sale' => 'weed', 'Cannabis oil' => 'cannabis', 'marijuana seeds' => 'marijuana', 'Hashish 10g' => 'hashish',
            'Xashiish iib ah' => 'xashiish', 'heroin' => 'heroin', 'Pure c0caine' => 'cocaine', 'Crystal   meth' => 'meth',
            'Tramadol 225mg tablets' => 'tramadol', 'Kiniinka TRAMADOOL' => 'tramadool', 'codeine syrup' => 'codeine',
            'Daroogo jaban' => 'daroogo', 'Maandooriye la heli karo' => 'maandooriye', 'glass bong' => 'bong', 'Rolling papers 50 packs' => 'rolling papers',
        ];
        foreach ($caught as $text => $word) {
            $this->assertNotNull($found = SpamGuard::drugWord($text), "not caught: $text");
            $this->assertStringContainsString(explode(' ', $word)[0], (string) $found, $text);
        }
    }

    public function test_normal_words_are_not_caught(): void
    {
        foreach ([
            'Iska qaad hadda, qiimo jaban', // qaad = take
            'Jaad kasta waan haynaa', 'Jaadka ugu fiican ee kabaha', // jaad = kind / type
            'Chat on WhatsApp', 'Flights to Qatar', 'Khatar ma leh, waa ammaan', 'Seaweed snacks', 'Weed killer 5 litres', 'Weed  eater for the garden',
            'Hashtag printing', 'Hash brown maker', 'Crack screen repair', 'Method and methane', 'Heroine costume for girls',
            'Thermos flask', 'Bongo drums', 'Takeaway food', 'Grinder for coffee', 'Pharmacy shelves and drug store fridge',
            'Ecstasy perfume 100ml',
        ] as $text) {
            $this->assertNull(SpamGuard::drugWord($text), "false alarm: $text");
        }
    }

    public function test_the_list_is_editable_in_super_admin_settings(): void
    {
        $this->asStaff();
        $current = $this->getJson('/admin/api/settings/general')->assertOk()->json();
        $this->assertStringContainsString("khat\n", $current['drug_words']);

        $this->putJson('/admin/api/settings/general', array_merge($current, ['drug_words' => "khat\nBIRAD\n  \nkhat"]))->assertOk();
        $this->assertSame(['khat', 'birad'], SpamGuard::drugWords());
        $this->assertNotNull(SpamGuard::drugWord('Birad 10 boxes'));
        $this->assertNull(SpamGuard::drugWord('Tramadol'), 'removed from the list');

        $this->asStaff('moderator');
        $this->putJson('/admin/api/settings/general', array_merge($current, ['drug_words' => '']))->assertForbidden();
        $this->assertSame(['khat', 'birad'], SpamGuard::drugWords());
    }

    // ---- Posting and editing ----

    public function test_posting_is_refused_with_a_clear_message_in_both_languages(): void
    {
        $this->postCar(['title' => 'Toyota and fresh khat'])->assertSessionHasErrors(['title' => __('ads.safety.drugs', [], 'en')]);
        $this->postCar(['description' => 'Waxaan iibinaa qaadka iyo baabuurta.'], '/so')->assertSessionHasErrors(['description' => __('ads.safety.drugs', [], 'so')]);
        $this->assertSame(0, Ad::count());
        $this->assertStringContainsString('Drugs are not allowed', __('ads.safety.drugs', [], 'en'));
        $this->assertStringContainsString('Daroogo laguma oggola', __('ads.safety.drugs', [], 'so'));

        $this->postCar(['title' => 'Weed killer and Toyota spare parts'])->assertSessionHasNoErrors();
        $this->assertSame(1, Ad::count());
    }

    public function test_edits_are_checked_too(): void
    {
        $ad = $this->live('Toyota Vitz clean');
        $this->actingAs($this->seller)->put("/account/ads/{$ad->id}", [
            'title' => 'Toyota Vitz + tramadol', 'description' => $ad->description, 'price' => 6500, 'condition' => 'used',
            'city_id' => $ad->city_id, 'fields' => $ad->fields, 'photos' => [],
        ])->assertSessionHasErrors('title');
        $this->assertSame('Toyota Vitz clean', $ad->fresh()->title);
    }

    public function test_switch_off_allows_it(): void
    {
        app(FeatureManager::class)->set('safety.no_drugs', false);
        $this->postCar(['title' => 'Toyota and fresh khat'])->assertSessionHasNoErrors();
    }

    // ---- Reports ----

    public function test_people_can_report_drugs(): void
    {
        $ad = $this->live('Some ad');
        $buyer = User::factory()->completed()->create();

        $this->actingAs($buyer)->get($ad->url())->assertOk()->assertSee('Drugs (khat, cannabis, pills…)');
        $this->actingAs($buyer)->post("/ads/{$ad->id}/report", ['reason' => 'drugs'])->assertRedirect();
        $this->assertSame('drugs', Report::sole()->reason);
        auth()->logout();
        $this->get('/so'.parse_url($ad->url(), PHP_URL_PATH))->assertOk()->assertSee('Daroogo (qaad, xashiish, kiniin…)');
        $this->post("/ads/{$ad->id}/report-guest", ['reason' => 'drugs', 'contact' => '', 't' => GuestReports::token(now()->subSeconds(30)->getTimestamp())])->assertRedirect();
        $this->assertSame(2, Report::where('reason', 'drugs')->count());

        app(FeatureManager::class)->set('safety.no_drugs', false);
        $this->actingAs($buyer)->post("/ads/{$ad->id}/report", ['reason' => 'drugs'])->assertSessionHasErrors('reason');
    }

    // ---- Existing ads and the admin queue ----

    public function test_check_command_sends_live_drug_ads_back_for_checking(): void
    {
        $bad = $this->live('Qaadka cusub ee Hargeysa');
        $alsoBad = $this->live('Phone case', 'Also selling c0deine syrup.');
        $fine = $this->live('Iska qaad baabuurkan', 'Jaad kasta waan haynaa.');
        $cars = Category::where('slug', 'cars')->first();
        AdCounters::refreshAll();
        $before = (int) $cars->fresh()->ads_count;

        $this->artisan('iibsom:drugs:check --dry-run')->expectsOutputToContain('Would send 2 ad(s)')->assertSuccessful()->run();
        $this->assertSame(AdStatus::Live, $bad->fresh()->status);

        $this->artisan('iibsom:drugs:check')->expectsOutputToContain('Sent 2 ad(s)')->assertSuccessful()->run();
        $this->assertSame(AdStatus::Pending, $bad->fresh()->status);
        $this->assertSame(['drugs', 'qaadka'], [$bad->fresh()->flag, $bad->fresh()->flag_word]);
        $this->assertSame('codeine', $alsoBad->fresh()->flag_word);
        $this->assertSame(AdStatus::Live, $fine->fresh()->status);
        $this->assertNull($fine->fresh()->flag);
        $this->assertSame($before - 2, (int) $cars->fresh()->ads_count, 'totals recounted');
        $this->assertNotNull(Ad::withTrashed()->find($bad->id), 'nothing deleted');
        $this->get($bad->url())->assertNotFound();
    }

    public function test_admin_drugs_filter_and_approve_clears_the_flag(): void
    {
        $flagged = $this->live('Qaadka cusub');
        $reported = $this->live('Reported ad');
        $other = $this->live('Normal ad');
        $this->artisan('iibsom:drugs:check')->assertSuccessful()->run();
        (new Report)->forceFill(['ad_id' => $reported->id, 'reason' => 'drugs', 'status' => 'waiting'])->save();

        $this->asStaff('moderator');
        $ids = collect($this->getJson('/admin/api/ads?flag=drugs')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$flagged->id, $reported->id], $ids);
        $this->assertNotContains($other->id, $ids);
        $this->getJson("/admin/api/ads/{$flagged->id}")->assertOk()->assertJsonPath('flag', 'Drug check: “qaadka”');
        $this->getJson('/admin/api/ads?flag=hack')->assertStatus(422);

        // A false alarm: staff approve it and the flag goes.
        $this->postJson("/admin/api/ads/{$flagged->id}/approve")->assertOk();
        $this->assertNull($flagged->fresh()->flag);
        $this->assertSame(AdStatus::Live, $flagged->fresh()->status);
    }

    // ---- Posting rules ----

    public function test_posting_rules_explain_the_drug_ban_in_both_languages(): void
    {
        $this->get('/help/posting-rules')->assertOk()->assertSee('Not allowed: drugs')->assertSee('Khat')->assertSee('Report this ad → Drugs', false);
        $this->get('/so/help/posting-rules')->assertOk()->assertSee('Lama oggola: daroogo')->assertSee('Qaad');
    }

    public function test_no_demo_text_matches_the_drug_list(): void
    {
        $texts = [];
        $content = require base_path('database/demo/content.php');
        array_walk_recursive($content, function ($v) use (&$texts) {
            if (is_string($v)) {
                $texts[] = $v;
            }
        });
        $this->assertGreaterThan(300, count($texts));
        foreach ($texts as $text) {
            $this->assertNull(SpamGuard::drugWord($text), "demo text matches the drug list: $text");
        }
    }
}
