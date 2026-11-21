<?php

namespace Tests\Feature\Admin;

use App\Models\AdminActivity;
use App\Models\Shop;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Support\Files\PrivateFiles;
use App\Support\Settings\SettingsManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** PR 107: ID photos are private, logged on every look, and deleted as soon as they are not needed. */
class VerificationSafetyTest extends AdminTestCase
{
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seller = User::factory()->completed()->create(['name' => 'Amina Seller']);
    }

    private function sendId(?User $user = null): VerificationRequest
    {
        $this->actingAs($user ?? $this->seller)->post('/account/verify', [
            'id_type' => 'passport',
            'photo' => UploadedFile::fake()->image('id.jpg', 1200, 800),
            'agree' => '1',
        ])->assertRedirect();
        auth()->logout();

        return VerificationRequest::latest('id')->firstOrFail();
    }

    public function test_the_photo_is_encrypted_on_the_private_disk_and_has_no_public_address(): void
    {
        $v = $this->sendId();
        $path = (string) $v->file_path;

        $this->assertStringStartsWith('verifications/', $path);
        Storage::disk('local')->assertExists($path);
        $raw = (string) Storage::disk('local')->get($path);
        $this->assertStringNotContainsString("\xFF\xD8\xFF", $raw); // not a readable JPEG
        $this->assertStringStartsWith("\xFF\xD8\xFF", (string) $v->photoBytes()); // only the app can read it
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertFalse(config('filesystems.disks.local.serve'));

        // No web address opens it.
        $this->get('/storage/'.$path)->assertNotFound();
        $this->get('/storage/app/private/'.$path)->assertNotFound();
        $this->get('/'.$path)->assertNotFound();
    }

    public function test_only_staff_allowed_to_check_ids_can_open_it_and_never_cached(): void
    {
        $v = $this->sendId();
        $url = "/admin/api/verifications/{$v->id}/photo";

        $this->get($url)->assertUnauthorized(); // guest
        $this->actingAs($this->seller)->get($url)->assertUnauthorized(); // the seller, signed in on the site
        auth()->logout();

        $this->asStaff('support');
        $this->get($url)->assertForbidden();

        $mod = $this->asStaff('moderator');
        $res = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $res->headers->get('Cache-Control'));
        $res->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        // Every look is logged: who, when, whose.
        $this->get($url)->assertOk();
        $logs = AdminActivity::where('action', 'verification.viewed')->get();
        $this->assertCount(2, $logs);
        $this->assertSame([$mod->id, $mod->id], $logs->pluck('user_id')->all());
        $this->assertStringContainsString('Amina Seller', (string) $logs->first()->summary);
    }

    public function test_photo_is_deleted_right_after_approve_or_reject_and_only_the_decision_stays(): void
    {
        $staff = $this->asStaff('moderator');
        $approved = $this->sendId();
        $this->asStaff('moderator'); // sendId signed out
        $path = (string) $approved->file_path;
        $this->postJson("/admin/api/verifications/{$approved->id}/approve")->assertOk();

        Storage::disk('local')->assertMissing($path);
        $approved->refresh();
        $this->assertNull($approved->file_path);
        $this->assertSame('approved', $approved->status);
        $this->assertNotNull($approved->reviewed_at);
        $this->assertNotNull($approved->reviewed_by);
        $this->getJson("/admin/api/verifications/{$approved->id}/photo")->assertNotFound();

        $other = User::factory()->completed()->create();
        $rejected = $this->sendId($other);
        $this->asStaff('moderator');
        $path = (string) $rejected->file_path;
        $this->postJson("/admin/api/verifications/{$rejected->id}/reject", ['reason' => 'Photo is blurry'])->assertOk();
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($rejected->fresh()->file_path);
        $this->assertSame([], Storage::disk('local')->allFiles(VerificationRequest::FOLDER));
        $this->assertNotNull($staff);
    }

    public function test_unchecked_requests_end_after_30_days_and_stray_files_go(): void
    {
        $old = $this->sendId();
        $this->travel(VerificationRequest::KEEP_PENDING_DAYS + 1)->days();
        $fresh = $this->sendId(User::factory()->completed()->create());

        // A decided request that still has a file, and a file with no request at all.
        $leftover = $this->sendId(User::factory()->completed()->create());
        $leftover->forceFill(['status' => 'approved'])->save();
        $stray = PrivateFiles::put(VerificationRequest::FOLDER, 'not linked to anything');
        Storage::disk('local')->put('receipts/keep-me.bin', 'other private files are not touched');

        $this->artisan('iibsom:verifications:clean')->assertSuccessful();

        $old->refresh();
        $this->assertSame('expired', $old->status);
        $this->assertNull($old->file_path);
        $this->assertNull($leftover->fresh()->file_path);
        Storage::disk('local')->assertMissing($stray);
        Storage::disk('local')->assertExists('receipts/keep-me.bin');

        $fresh->refresh();
        $this->assertSame('pending', $fresh->status); // still waiting, still has its photo
        Storage::disk('local')->assertExists((string) $fresh->file_path);
        $this->assertSame([$fresh->file_path], Storage::disk('local')->allFiles(VerificationRequest::FOLDER));

        // The seller sees why and can send again.
        $this->actingAs($this->seller)->get('/account/verify')->assertOk()->assertSee('data-verify-expired', false)->assertSee('name="photo"', false);
    }

    public function test_deleting_a_user_deletes_their_id_photos(): void
    {
        $v = $this->sendId();
        $path = (string) $v->file_path;

        $this->seller->delete();

        Storage::disk('local')->assertMissing($path);
        $this->assertSame(0, VerificationRequest::count());
    }

    public function test_it_is_free_for_people_and_the_business_price_stays_zero_by_default(): void
    {
        $this->assertSame(0, (int) setting('verification.business_price'));
        $this->actingAs($this->seller)->get('/account/verify')->assertOk()->assertSee('Free.');

        $shopOwner = User::factory()->completed()->create();
        $shop = new Shop(['name' => 'Abdi Motors', 'slug' => 'abdi-motors']);
        $shop->user_id = $shopOwner->id;
        $shop->save();
        $this->actingAs($shopOwner)->get('/account/verify')->assertOk()->assertSee('Free.');

        app(SettingsManager::class)->set('verification.business_price', 5);
        $this->actingAs($shopOwner)->get('/account/verify')->assertOk()->assertSee('For businesses the badge costs $5');
        $this->actingAs($this->seller)->get('/account/verify')->assertOk()->assertSee('Free.')->assertDontSee('$5');
    }

    public function test_business_price_setting_is_checked(): void
    {
        $this->asStaff();
        $values = $this->getJson('/admin/api/settings/general')->assertOk()->json();
        $this->assertSame(0, $values['verify_business_price']);

        $this->putJson('/admin/api/settings/general', ['verify_business_price' => -1] + $values)->assertUnprocessable();
        $this->putJson('/admin/api/settings/general', ['verify_business_price' => 1001] + $values)->assertUnprocessable();
        $this->putJson('/admin/api/settings/general', ['verify_business_price' => 10] + $values)->assertOk();
        $this->assertSame(10, (int) setting('verification.business_price'));
    }
}
