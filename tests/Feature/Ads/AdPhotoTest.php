<?php

namespace Tests\Feature\Ads;

use App\Jobs\ProcessAdPhoto;
use App\Models\AdPhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdPhotoTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
        Storage::fake('public');
        config(['iibsom.photos_disk' => 'public']);
        $this->seller = User::factory()->completed()->create();
    }

    public function test_upload_queues_processing(): void
    {
        Queue::fake();

        $this->actingAs($this->seller)
            ->postJson('/post-photos', ['photo' => UploadedFile::fake()->image('car.jpg', 1200, 900)])
            ->assertCreated()->assertJsonStructure(['id', 'status']);

        $photo = AdPhoto::first();
        $this->assertSame($this->seller->id, $photo->user_id);
        Storage::disk('local')->assertExists($photo->upload_path);
        Queue::assertPushed(ProcessAdPhoto::class);
    }

    public function test_processing_makes_sizes_and_deletes_the_original(): void
    {
        $this->actingAs($this->seller)
            ->postJson('/post-photos', ['photo' => UploadedFile::fake()->image('car.jpg', 2400, 1800)])
            ->assertCreated(); // queue is "sync" in tests → processed now

        $photo = AdPhoto::first();
        $this->assertSame('ready', $photo->status);
        $this->assertNull($photo->upload_path);
        $this->assertSame(1600, $photo->width);
        foreach (['thumb', 'medium', 'large'] as $size) {
            Storage::disk('public')->assertExists($photo->variants[$size]['webp']);
        }
        $this->assertSame([], Storage::disk('local')->allFiles('uploads'));
        $this->assertStringContainsString('/storage/ads/', $photo->url('thumb'));
    }

    public function test_processed_photo_has_no_hidden_data(): void
    {
        // A JPEG with an EXIF comment (stands in for GPS data).
        $jpeg = UploadedFile::fake()->image('gps.jpg', 800, 600);
        $this->actingAs($this->seller)->postJson('/post-photos', ['photo' => $jpeg])->assertCreated();

        $bytes = Storage::disk('public')->get(AdPhoto::first()->variants['large']['webp']);
        $this->assertStringStartsWith('RIFF', $bytes); // re-encoded WebP
        $this->assertStringNotContainsString('Exif', $bytes);
    }

    public function test_non_images_are_refused(): void
    {
        $this->actingAs($this->seller)->postJson('/post-photos', ['photo' => UploadedFile::fake()->create('virus.php', 10, 'image/jpeg')])->assertUnprocessable();
        $this->actingAs($this->seller)->postJson('/post-photos', ['photo' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])->assertUnprocessable();
        $this->assertSame(0, AdPhoto::count());
    }

    public function test_too_big_or_too_small_is_refused(): void
    {
        $this->actingAs($this->seller)->postJson('/post-photos', ['photo' => UploadedFile::fake()->image('big.jpg', 1000, 1000)->size(13000)])->assertUnprocessable();
        $this->actingAs($this->seller)->postJson('/post-photos', ['photo' => UploadedFile::fake()->image('tiny.jpg', 50, 50)])->assertUnprocessable();
    }

    public function test_guests_cannot_upload(): void
    {
        $this->postJson('/post-photos', ['photo' => UploadedFile::fake()->image('car.jpg')])->assertUnauthorized();
    }

    public function test_you_can_delete_only_your_own_unused_photo(): void
    {
        $mine = AdPhoto::create(['user_id' => $this->seller->id]);
        $theirs = AdPhoto::create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($this->seller)->deleteJson('/post-photos/'.$theirs->id)->assertNotFound();
        $this->assertNotNull($theirs->fresh());

        $this->actingAs($this->seller)->deleteJson('/post-photos/'.$mine->id)->assertOk();
        $this->assertNull($mine->fresh());
    }

    public function test_too_many_waiting_photos(): void
    {
        Queue::fake();
        foreach (range(1, 24) as $i) {
            AdPhoto::create(['user_id' => $this->seller->id]);
        }

        $this->actingAs($this->seller)->postJson('/post-photos', ['photo' => UploadedFile::fake()->image('car.jpg', 800, 600)])->assertStatus(429);
    }
}
