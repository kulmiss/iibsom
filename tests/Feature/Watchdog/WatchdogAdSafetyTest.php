<?php

namespace Tests\Feature\Watchdog;

use App\Enums\AdStatus;
use App\Jobs\ProcessAdPhoto;
use App\Models\Ad;
use App\Models\AdPhoto;
use App\Models\Category;
use App\Models\User;
use App\Models\WatchdogProblem;
use App\Support\Features\FeatureManager;
use App\Support\Watchdog\Runner;
use App\Support\Watchdog\Watchdog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use Tests\TestCase;

/** PR 95: ad safety — drug words, spikes, reports, copy spam, waiting ads, scam prices ("please check"). */
class WatchdogAdSafetyTest extends TestCase
{
    use RefreshDatabase, WatchdogTesting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->setUpWatchdog();
    }

    private function check(string $group = 'frequent'): void
    {
        app(Runner::class)->run($group);
    }

    /** @return list<string> */
    private function open(string $prefix): array
    {
        return WatchdogProblem::open()->where('key', 'like', $prefix.'%')->orderBy('key')->pluck('key')->all();
    }

    private function cars(): int
    {
        return (int) Category::where('slug', 'cars')->value('id');
    }

    public function test_drug_words_in_live_ads_go_back_for_checking_and_are_told_once(): void
    {
        $this->check(); // first look: from now on only changed ads are read
        $ad = Ad::factory()->live()->create(['title' => 'Fresh khat every morning']);
        $clean = Ad::factory()->live()->create(['title' => 'Toyota Hilux 2016']);
        $this->check();
        $this->check();

        $this->assertSame(AdStatus::Pending, $ad->fresh()->status);
        $this->assertSame('drugs', $ad->fresh()->flag);
        $this->assertSame(AdStatus::Live, $clean->fresh()->status);
        $this->assertSame(1, substr_count(implode("\n", $this->telegrams()), '1 live ad(s) had drug words'));
        $this->assertSame($ad->id, WatchdogProblem::where('key', 'like', 'ads.drugs:%')->value('subject_id'));
    }

    public function test_drug_check_follows_the_no_drugs_switch(): void
    {
        app(FeatureManager::class)->set('safety.no_drugs', false);
        $ad = Ad::factory()->live()->create(['title' => 'Fresh khat every morning']);
        $this->check();

        $this->assertSame(AdStatus::Live, $ad->fresh()->status);
    }

    public function test_drugs_check_command_still_works(): void
    {
        $ad = Ad::factory()->live()->create(['title' => 'Fresh khat every morning']);
        $this->artisan('iibsom:drugs:check --dry-run')->expectsOutputToContain('Would send 1 ad(s)')->assertSuccessful();
        $this->assertSame(AdStatus::Live, $ad->fresh()->status);
        $this->artisan('iibsom:drugs:check')->expectsOutputToContain('Sent 1 ad(s)')->assertSuccessful();
        $this->assertSame(AdStatus::Pending, $ad->fresh()->status);
    }

    public function test_many_ads_from_one_user_or_one_phone_in_an_hour(): void
    {
        $this->checks(['ads.spikes' => ['limit' => 3]]);
        $user = User::factory()->completed()->create(['phone' => '+252612222222']);
        Ad::factory()->count(3)->for($user)->create();
        $other = User::factory()->completed()->create(['phone' => '+252613333333']);
        $twin = User::factory()->completed()->create(['phone' => '+252613333333']);
        Ad::factory()->count(2)->for($other)->create();
        Ad::factory()->for($twin)->create();
        $this->check();

        $this->assertSame(["ads.spikes:phone:{$other->id}", "ads.spikes:user:{$user->id}"], $this->open('ads.spikes'));
        $this->assertStringNotContainsString('613333333', WatchdogProblem::all()->toJson()); // never the number
        $this->assertStringNotContainsString('613333333', implode("\n", $this->telegrams()));

        $this->travel(61)->minutes();
        $this->check();
        $this->assertSame([], $this->open('ads.spikes'));
    }

    public function test_many_reports_on_one_ad_or_one_seller(): void
    {
        $this->checks(['ads.reports' => ['limit' => 2]]);
        $one = Ad::factory()->live()->create();
        $seller = User::factory()->completed()->create();
        [$a, $b] = Ad::factory()->count(2)->live()->for($seller)->create()->all();
        foreach ([[$one, 2], [$a, 1], [$b, 1]] as [$ad, $times]) {
            foreach (range(1, $times) as $i) {
                DB::table('reports')->insert(['ad_id' => $ad->id, 'user_id' => User::factory()->create()->id, 'reason' => 'scam', 'status' => 'waiting', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        $this->check();

        $this->assertSame(["ads.reports:ad:{$one->id}", "ads.reports:seller:{$seller->id}"], $this->open('ads.reports'));
    }

    public function test_copy_spam_same_photo_or_same_text(): void
    {
        $this->checks(['ads.copies' => ['limit' => 3]]);
        $ads = Ad::factory()->count(3)->live()->create();
        foreach ($ads as $ad) {
            AdPhoto::factory()->create(['ad_id' => $ad->id, 'user_id' => $ad->user_id, 'phash' => 'a1b2c3d4e5f60718']);
        }
        $text = 'Brand new iPhone 15 Pro Max, sealed box, call now for the best price in town.';
        Ad::factory()->count(3)->create(['description' => $text]);
        Ad::factory()->count(2)->create(['description' => 'Short']); // too short to count
        $this->check('hourly');

        $keys = $this->open('ads.copies');
        $this->assertContains('ads.copies:photo:a1b2c3d4e5f60718', $keys);
        $this->assertCount(2, $keys);
        $this->assertStringContainsString('The same text is in 3 new ads', implode("\n", $this->telegrams()));
    }

    public function test_photo_fingerprint_is_the_same_after_saving_again_and_differs_for_another_photo(): void
    {
        $manager = new ImageManager(new GdDriver);
        $photo = (string) file_get_contents(base_path('database/demo/photos/alloy-wheel-1.jpg'));
        $savedAgain = (string) $manager->read($photo)->toJpeg(70);
        $other = (string) file_get_contents(base_path('database/demo/photos/air-conditioner-unit-1.jpg'));

        $hash = ProcessAdPhoto::fingerprint($manager, $photo);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $hash);
        $this->assertSame($hash, ProcessAdPhoto::fingerprint($manager, $savedAgain));
        $this->assertNotSame($hash, ProcessAdPhoto::fingerprint($manager, $other));
    }

    public function test_ads_waiting_too_long_for_approval(): void
    {
        $this->checks(['ads.queue' => ['limit' => 12]]);
        Ad::factory()->create(['updated_at' => now()->subHours(11)]);
        $this->check();
        $this->assertSame([], $this->open('ads.queue'));

        Ad::factory()->create(['updated_at' => now()->subHours(13)]);
        $this->check();
        $this->assertSame(['ads.queue'], $this->open('ads.queue'));
        $this->assertStringContainsString('1 ad(s) waiting more than 12 hours', implode("\n", $this->telegrams()));
    }

    public function test_scam_price_is_a_please_check_item_only_and_stays_checked(): void
    {
        foreach (range(1, 6) as $i) {
            Ad::factory()->live()->create(['category_id' => $this->cars(), 'price' => 10000 + $i * 100, 'created_at' => now()->subDays(10)]);
        }
        $cheap = Ad::factory()->live()->create(['category_id' => $this->cars(), 'price' => 900]);
        $fair = Ad::factory()->live()->create(['category_id' => $this->cars(), 'price' => 9800]);
        $this->check('hourly');

        $this->assertSame(["ads.prices:{$cheap->id}"], $this->open('ads.prices'));
        $problem = WatchdogProblem::open()->where('key', "ads.prices:{$cheap->id}")->firstOrFail();
        $this->assertSame('info', $problem->level);
        $this->assertSame(['ad', $cheap->id], [$problem->subject_type, $problem->subject_id]);
        $this->assertSame([], $this->telegrams()); // not sent: digest and the Watchdog page only
        $this->assertSame(AdStatus::Live, $cheap->fresh()->status); // nothing hidden
        $this->assertNotContains("ads.prices:{$fair->id}", $this->open('ads.prices'));

        app(Watchdog::class)->markFixed($problem, User::factory()->create());
        $this->check('hourly');
        $this->assertSame([], $this->open('ads.prices'));
    }
}
