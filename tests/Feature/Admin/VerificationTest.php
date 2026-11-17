<?php

namespace Tests\Feature\Admin;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Models\VerificationRequest;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

class VerificationTest extends AdminTestCase
{
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->seller = User::factory()->completed()->create(['name' => 'Amina Seller']);
    }

    private function on(): void
    {
        app(FeatureManager::class)->set('verified_badge', true);
    }

    private function send(?User $user = null, array $extra = []): TestResponse
    {
        return $this->actingAs($user ?? $this->seller)->post('/account/verify', $extra + [
            'id_type' => 'national_id',
            'photo' => UploadedFile::fake()->image('id.jpg', 1200, 800),
            'agree' => '1',
        ]);
    }

    public function test_off_by_default_and_hidden_everywhere(): void
    {
        $this->assertFalse(feature('verified_badge'));
        $this->actingAs($this->seller)->get('/account/verify')->assertNotFound();
        $this->send()->assertNotFound();
        $this->asStaff();
        $this->getJson('/admin/api/verifications')->assertNotFound();
        $this->getJson('/admin/api/me')->assertJsonPath('user.verified_badge', false);
        // A badge given earlier is hidden while the switch is OFF.
        $this->seller->forceFill(['verified_at' => now()])->save();
        $this->assertFalse($this->seller->fresh()?->isVerified());
    }

    public function test_seller_sends_an_id_photo_kept_encrypted_and_private(): void
    {
        $this->on();
        $this->actingAs($this->seller)->get('/account/verify')->assertOk()->assertSee('Get the Verified badge')->assertSee('enctype="multipart/form-data"', false);
        $this->actingAs($this->seller)->get('/so/account/verify')->assertOk()->assertSee('Hel calaamadda La Xaqiijiyey');

        $this->send()->assertRedirect(url('/account/verify'))->assertSessionHas('status', 'Thank you. Our team will check your ID soon.');

        $v = VerificationRequest::firstOrFail();
        $this->assertSame('pending', $v->status);
        $this->assertStringStartsWith('verifications/', (string) $v->file_path);
        $stored = Storage::disk('local')->get((string) $v->file_path);
        $this->assertStringNotContainsString("\xFF\xD8\xFF", (string) $stored, 'The file on disk must be encrypted, not a plain JPEG.');
        $this->assertStringStartsWith("\xFF\xD8\xFF", (string) $v->photoBytes(), 'Decrypted it is a (re-encoded) JPEG.');

        $this->actingAs($this->seller)->get('/account/verify')->assertSee('We are checking your ID.');
        // Only one request at a time.
        $this->send();
        $this->assertSame(1, VerificationRequest::count());
    }

    public function test_bad_uploads_are_refused(): void
    {
        $this->on();

        $this->send(null, ['photo' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf')])->assertSessionHasErrors('photo');
        $this->send(null, ['photo' => UploadedFile::fake()->image('small.jpg', 100, 80)])->assertSessionHasErrors('photo');
        $this->send(null, ['id_type' => 'library_card'])->assertSessionHasErrors('id_type');
        $this->send(null, ['agree' => null])->assertSessionHasErrors(['agree' => 'Please tick the box to agree.']);
        $this->assertSame(0, VerificationRequest::count());
    }

    public function test_staff_approve_badge_shows_and_photo_is_deleted(): void
    {
        $this->on();
        $this->send();
        $v = VerificationRequest::firstOrFail();
        $path = (string) $v->file_path;

        $staff = $this->asStaff('moderator');
        $this->getJson('/admin/api/verifications')->assertOk()->assertJsonPath('pending', 1)->assertJsonPath('data.0.user.name', 'Amina Seller')->assertJsonPath('data.0.has_photo', true);
        $photo = $this->get("/admin/api/verifications/{$v->id}/photo")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('no-store', (string) $photo->headers->get('Cache-Control'));
        $this->assertDatabaseHas('admin_activities', ['action' => 'verification.viewed', 'user_id' => $staff->id]);

        $this->postJson("/admin/api/verifications/{$v->id}/approve")->assertOk();

        $this->assertSame('approved', $v->fresh()?->status);
        $this->assertNull($v->fresh()?->file_path);
        Storage::disk('local')->assertMissing($path);
        $this->assertTrue($this->seller->fresh()?->isVerified());
        // Twice = refused (no double decisions).
        $this->postJson("/admin/api/verifications/{$v->id}/approve")->assertStatus(409);
        $this->get("/admin/api/verifications/{$v->id}/photo")->assertNotFound();

        $ad = Ad::factory()->live()->create(['user_id' => $this->seller->id, 'category_id' => Category::where('slug', 'cars')->value('id'), 'city_id' => City::where('slug', 'mogadishu')->value('id')]);
        auth()->logout();
        $this->get($ad->url())->assertOk()->assertSee('Our team checked this seller&#039;s ID', false)->assertSee('Verified');
    }

    public function test_staff_reject_with_reason_and_seller_can_try_again(): void
    {
        $this->on();
        $this->send();
        $v = VerificationRequest::firstOrFail();

        $this->asStaff('moderator');
        $this->postJson("/admin/api/verifications/{$v->id}/reject", ['reason' => ''])->assertUnprocessable();
        $this->postJson("/admin/api/verifications/{$v->id}/reject", ['reason' => 'Photo is blurry'])->assertOk();
        $this->assertNull($v->fresh()?->file_path);
        $this->postJson("/admin/api/verifications/{$v->id}/approve")->assertStatus(409);

        $this->actingAs($this->seller)->get('/account/verify')->assertOk()->assertSee('We could not verify your ID: Photo is blurry')->assertSee('Send for checking');
        $this->send();
        $this->assertSame(2, VerificationRequest::count());
        $this->assertFalse($this->seller->fresh()?->isVerified());
    }

    public function test_remove_badge_later(): void
    {
        $this->on();
        $this->seller->forceFill(['verified_at' => now()])->save();

        $this->asStaff('moderator');
        $this->getJson("/admin/api/users/{$this->seller->id}")->assertJsonPath('verified', true);
        $this->postJson("/admin/api/users/{$this->seller->id}/unverify")->assertOk();
        $this->assertNull($this->seller->fresh()?->verified_at);
        $this->postJson("/admin/api/users/{$this->seller->id}/unverify")->assertStatus(409);
        $this->assertDatabaseHas('admin_activities', ['action' => 'verification.revoked']);
    }

    public function test_only_staff_with_the_permission(): void
    {
        $this->on();
        $this->send();
        $v = VerificationRequest::firstOrFail();

        $this->asStaff('support');
        $this->getJson('/admin/api/verifications')->assertForbidden();
        $this->get("/admin/api/verifications/{$v->id}/photo")->assertForbidden();
        $this->postJson("/admin/api/verifications/{$v->id}/approve")->assertForbidden();

        auth()->logout();
        $this->get("/admin/api/verifications/{$v->id}/photo")->assertUnauthorized();
        // A customer (even the owner) can't open the photo.
        $this->actingAs($this->seller)->get("/admin/api/verifications/{$v->id}/photo")->assertUnauthorized();
        // The photo is never in the public folder.
        $this->assertFileDoesNotExist(public_path('storage/'.$v->file_path));
    }

    public function test_deleting_the_user_removes_their_requests(): void
    {
        $this->on();
        $this->send();

        $this->seller->delete();

        $this->assertSame(0, VerificationRequest::count());
    }
}
