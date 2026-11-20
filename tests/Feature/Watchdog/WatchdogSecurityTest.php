<?php

namespace Tests\Feature\Watchdog;

use App\Models\Ad;
use App\Models\BlockedPhone;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Models\WatchdogProblem;
use App\Support\Watchdog\Runner;
use App\Support\Watchdog\StaffDevices;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Laravel\Socialite\Facades\Socialite;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;
use Tests\Feature\Admin\AdminTestCase;

/** PR 95: security checks — failed sign-ins, staff changes, sign-up spikes, banned people, code and upload files. */
class WatchdogSecurityTest extends AdminTestCase
{
    use WatchdogTesting;

    protected function setUp(): void
    {
        parent::setUp();
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

    public function test_many_failed_staff_sign_ins_are_urgent_and_never_name_the_email(): void
    {
        $this->check(); // the staff check starts from "now" on its first run
        foreach (range(1, 10) as $i) {
            $this->postJson('/admin/api/login', ['email' => "guess{$i}@iibsom.test", 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->check();

        $this->assertSame(['security.logins:staff'], $this->open('security.logins'));
        $text = implode("\n", $this->telegrams());
        $this->assertStringContainsString('10 failed staff sign-ins in 15 minutes', $text);
        $this->assertStringNotContainsString('guess1@iibsom.test', $text);

        $this->travel(20)->minutes();
        $this->check();
        $this->assertSame([], $this->open('security.logins'));
    }

    public function test_wrong_2fa_codes_count_too(): void
    {
        $this->checks(['security.logins' => ['limit' => 2]]);
        $user = $this->staff();
        $this->postJson('/admin/api/login', ['email' => $user->email, 'password' => 'correct-horse-battery-9'])->assertOk();
        $this->postJson('/admin/api/2fa/verify', ['code' => '000000'])->assertUnprocessable();
        $this->postJson('/admin/api/2fa/verify', ['code' => '000001'])->assertUnprocessable();
        $this->check();

        $this->assertSame(['security.logins:staff'], $this->open('security.logins'));
    }

    public function test_failed_google_sign_ins_are_counted(): void
    {
        $this->checks(['security.logins' => ['limit' => 1]]); // users: warning at 3× the limit
        Socialite::shouldReceive('driver->user')->andThrow(new RuntimeException('Google said no'));
        foreach (range(1, 3) as $i) {
            $this->get('/auth/google/callback?code=x&state=y')->assertRedirect();
        }
        $this->check();

        $this->assertSame(['security.logins:users'], $this->open('security.logins'));
        $this->assertSame('warning', WatchdogProblem::open()->where('key', 'security.logins:users')->first()->level);
    }

    public function test_new_browser_for_the_super_admin_and_staff_changes_are_told_once(): void
    {
        $this->check(); // start point
        $owner = $this->staff();
        $this->postJson('/admin/api/login', ['email' => $owner->email, 'password' => 'correct-horse-battery-9']);
        $this->postJson('/admin/api/2fa/verify', ['code' => (new Google2FA)->getCurrentOtp($owner->two_factor_secret)])->assertOk()
            ->assertCookie(StaffDevices::COOKIE);
        $this->assertSame(1, DB::table('staff_devices')->where('user_id', $owner->id)->count());
        $this->assertStringNotContainsString('127.0.0.1', DB::table('staff_devices')->get()->toJson());

        $this->postJson('/admin/api/staff', ['name' => 'New Mod', 'email' => 'mod@iibsom.test', 'role' => 'moderator'])->assertCreated();
        $mod = User::where('email', 'mod@iibsom.test')->firstOrFail();
        $this->putJson("/admin/api/staff/{$mod->id}", ['role' => 'support'])->assertOk();
        $this->postJson("/admin/api/staff/{$mod->id}/reset-2fa")->assertOk();
        $this->check();
        $this->check();

        $texts = implode("\n", $this->telegrams());
        foreach (['signed in from a new browser', 'A new staff account was made', 'A staff role was changed', '2-step login was reset'] as $part) {
            $this->assertSame(1, substr_count($texts, $part), $part);
        }
        $this->assertStringNotContainsString('mod@iibsom.test', $texts); // names, not email addresses
        $this->assertSame(1, WatchdogProblem::where('key', 'like', 'security.staff:staff.signed_in:%')->count()); // digest only
        $this->assertStringNotContainsString("What happened: {$owner->name} signed in to the admin.", $texts); // the sign-in itself: digest only
    }

    public function test_a_broken_new_browser_note_never_stops_a_sign_in(): void
    {
        // E.g. the staff_devices table is not migrated yet on the owner's computer (2026-11-20).
        $this->mock(StaffDevices::class)->shouldReceive('signedIn')->andThrow(new RuntimeException('Table staff_devices does not exist'));
        $owner = $this->staff();
        $this->postJson('/admin/api/login', ['email' => $owner->email, 'password' => 'correct-horse-battery-9'])->assertOk();

        $this->postJson('/admin/api/2fa/verify', ['code' => (new Google2FA)->getCurrentOtp($owner->two_factor_secret)])
            ->assertOk()->assertJsonPath('user.email', $owner->email);
        $this->getJson('/admin/api/me')->assertOk();
    }

    public function test_the_same_browser_again_is_not_new(): void
    {
        $owner = $this->staff();
        $request = request();
        app(StaffDevices::class)->signedIn($request, $owner);
        $cookie = collect(app('cookie')->getQueuedCookies())->first(fn ($c) => $c->getName() === StaffDevices::COOKIE);
        $request->cookies->set(StaffDevices::COOKIE, $cookie->getValue());
        app(StaffDevices::class)->signedIn($request, $owner);

        $this->assertSame(1, DB::table('admin_activities')->where('action', 'staff.new_device')->count());
    }

    public function test_sudden_sign_ups(): void
    {
        $this->checks(['security.signups' => ['limit' => 5]]);
        User::factory()->count(4)->create();
        $this->check();
        $this->assertSame([], $this->open('security.signups'));

        User::factory()->create();
        $this->check();
        $this->assertSame(['security.signups'], $this->open('security.signups'));
    }

    public function test_banned_and_blocked_people_trying_again(): void
    {
        $this->checks(['security.banned' => ['limit' => 2]]);
        $user = User::factory()->completed()->create(['phone' => '+252611111111']);
        BlockedPhone::create(['phone' => '+252611111111', 'reason' => 'spam']);
        $category = Category::where('slug', 'cars')->firstOrFail();
        $post = fn () => $this->actingAs($user)->post("/post/{$category->slug}", ['title' => 'Toyota Hilux 2016', 'description' => 'Good car, clean inside and out.', 'price' => 9000, 'condition' => 'used', 'fields' => ['brand' => 'toyota'], 'city_id' => City::where('slug', 'mogadishu')->value('id')]);
        $post()->assertSessionHasErrors('title');
        $post()->assertSessionHasErrors('title');
        $this->check();

        $this->assertSame(['security.banned'], $this->open('security.banned'));
        $this->assertSame(0, Ad::count());
    }

    public function test_changed_code_files_are_told_once_and_baseline_accepts_an_update(): void
    {
        $this->check('daily'); // first night: the starting point
        $this->assertSame(0, WatchdogProblem::where('key', 'security.code_files')->count());

        $file = base_path('routes/watchdog-test-'.bin2hex(random_bytes(3)).'.php');
        File::put($file, "<?php\n");
        try {
            $this->check('daily');
            $this->check('daily');
            $this->assertSame(1, WatchdogProblem::where('key', 'security.code_files')->count());
            $this->assertStringContainsString('1 code file(s) changed', implode("\n", $this->telegrams()));

            File::put($file, "<?php\n// my update\n");
            $this->artisan('iibsom:watchdog:baseline')->assertSuccessful();
            $this->check('daily');
            $this->assertSame(1, WatchdogProblem::where('key', 'security.code_files')->count());
        } finally {
            File::delete($file);
        }
    }

    public function test_php_file_in_uploads_is_urgent_until_removed(): void
    {
        $file = storage_path('app/public/ads/watchdog-'.bin2hex(random_bytes(3)).'.php');
        File::ensureDirectoryExists(dirname($file));
        File::put($file, '<?php echo 1;');
        try {
            $this->check('hourly');
            $this->assertSame(['security.upload_php'], $this->open('security.upload_php'));
            $this->assertSame('urgent', WatchdogProblem::open()->where('key', 'security.upload_php')->first()->level);
        } finally {
            File::delete($file);
        }
        $this->check('hourly');
        $this->assertSame([], $this->open('security.upload_php'));
    }

    public function test_counters_keep_no_ip_and_end_within_a_day(): void
    {
        $this->postJson('/admin/api/login', ['email' => 'x@iibsom.test', 'password' => 'wrong'], ['REMOTE_ADDR' => '198.51.100.9']);
        $key = 'watchdog.signal.admin_login_failed.'.intdiv(now()->getTimestamp(), 300);
        $this->assertSame(1, (int) Cache::get($key));

        $this->travel(25)->hours();
        $this->assertNull(Cache::get($key));
    }
}
