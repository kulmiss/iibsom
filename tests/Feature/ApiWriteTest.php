<?php

namespace Tests\Feature;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\AdPhoto;
use App\Models\ApiToken;
use App\Models\Area;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiWriteTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private City $mogadishu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(FeatureManager::class)->set('api.write', true);
        $this->mogadishu = City::where('slug', 'mogadishu')->firstOrFail();
        $this->seller = User::factory()->completed()->create(['city_id' => $this->mogadishu->id]);
    }

    /** @param list<string> $abilities */
    private function token(?User $user = null, array $abilities = ['read', 'write']): string
    {
        return ApiToken::issue($user ?? $this->seller, 'Test app', $abilities)[1];
    }

    /** @return array<string, string> */
    private function auth(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    /** @return array<string, mixed> */
    private function carAd(array $overrides = []): array
    {
        return array_merge([
            'category' => 'cars',
            'title' => 'Toyota Vitz 2012 automatic',
            'description' => 'Very clean car, new tyres, AC works well. No accidents.',
            'price' => 6500,
            'condition' => 'used',
            'city_id' => $this->mogadishu->id,
            'area_id' => Area::where('slug', 'hodan')->value('id'),
            'fields' => ['brand' => 'toyota', 'model' => 'Vitz', 'year' => 2012, 'mileage' => 98000, 'fuel' => 'petrol', 'transmission' => 'automatic'],
        ], $overrides);
    }

    public function test_switch_off_means_404_everywhere(): void
    {
        app(FeatureManager::class)->set('api.write', false);
        $token = $this->token();

        $this->getJson('/api/v1/me', $this->auth($token))->assertNotFound();
        $this->postJson('/api/v1/ads', $this->carAd(), $this->auth($token))->assertNotFound();
        $this->actingAs($this->seller)->get('/account/api')->assertNotFound();
        $this->assertSame(0, Ad::count());
    }

    public function test_switch_is_off_by_default(): void
    {
        $this->assertFalse(config('features')['api.write']['default']);
    }

    public function test_no_wrong_or_expired_token_is_401(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized()->assertHeader('WWW-Authenticate', 'Bearer');
        $good = $this->token();
        [, $id, $secret] = explode('_', $good);

        $this->getJson('/api/v1/me', $this->auth("iib_{$id}_".str_repeat('a', 40)))->assertUnauthorized();
        $this->getJson('/api/v1/me', $this->auth('iib_999999_'.$secret))->assertUnauthorized();
        $this->getJson('/api/v1/me', $this->auth('garbage'))->assertUnauthorized();
        // A session cookie is not enough: the API only takes tokens.
        $this->actingAs($this->seller)->getJson('/api/v1/me')->assertUnauthorized();

        ApiToken::whereKey((int) $id)->update(['expires_at' => now()->subMinute()]);
        $this->getJson('/api/v1/me', $this->auth($good))->assertUnauthorized();
    }

    public function test_only_a_hash_is_stored(): void
    {
        $plain = $this->token();
        $row = ApiToken::firstOrFail();

        $this->assertStringNotContainsString(explode('_', $plain)[2], json_encode($row->getAttributes()) ?: '');
        $this->assertSame(hash('sha256', explode('_', $plain)[2]), $row->secret_hash);
        $this->assertArrayNotHasKey('secret_hash', $row->toArray());
    }

    public function test_me_and_last_used(): void
    {
        $token = $this->token();
        $this->getJson('/api/v1/me', $this->auth($token))->assertOk()
            ->assertJsonPath('data.id', $this->seller->id)
            ->assertJsonPath('data.name', $this->seller->name)
            ->assertJsonMissingPath('data.phone')
            ->assertJsonMissingPath('data.email');

        $this->assertNotNull(ApiToken::firstOrFail()->last_used_at);
    }

    public function test_post_an_ad_with_the_same_rules_as_the_website(): void
    {
        app(FeatureManager::class)->set('ads.approval', true);
        $token = $this->token();

        $res = $this->postJson('/api/v1/ads', $this->carAd(), $this->auth($token))->assertCreated()
            ->assertJsonPath('data.title', 'Toyota Vitz 2012 automatic')
            ->assertJsonPath('data.status', 'pending');
        $ad = Ad::findOrFail($res->json('data.id'));
        $this->assertSame($this->seller->id, $ad->user_id);
        $this->assertSame(AdStatus::Pending, $ad->status);

        // Same checks: missing fields, duplicate, parent category, unknown category, spam honeypot.
        $this->postJson('/api/v1/ads', $this->carAd(['title' => '']), $this->auth($token))->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->postJson('/api/v1/ads', $this->carAd(), $this->auth($token))->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->postJson('/api/v1/ads', $this->carAd(['category' => 'vehicles', 'title' => 'Another title here']), $this->auth($token))->assertNotFound();
        $this->postJson('/api/v1/ads', $this->carAd(['category' => 'nope']), $this->auth($token))->assertStatus(422);
        $this->assertSame(1, Ad::count());
    }

    public function test_daily_limit_applies_to_the_api_too(): void
    {
        app(SettingsManager::class)->set('ads.max_per_day', 1);
        $token = $this->token();

        $this->postJson('/api/v1/ads', $this->carAd(), $this->auth($token))->assertCreated();
        $this->postJson('/api/v1/ads', $this->carAd(['title' => 'Honda Fit 2014 hybrid']), $this->auth($token))
            ->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->assertSame(1, Ad::count());
    }

    public function test_read_only_token_cannot_write(): void
    {
        $token = $this->token(abilities: ['read']);

        $this->getJson('/api/v1/me/ads', $this->auth($token))->assertOk();
        $this->postJson('/api/v1/ads', $this->carAd(), $this->auth($token))->assertForbidden();
        $this->assertSame(0, Ad::count());
    }

    public function test_banned_or_unfinished_profile_is_refused(): void
    {
        $banned = User::factory()->completed()->banned()->create();
        $this->getJson('/api/v1/me', $this->auth($this->token($banned)))->assertForbidden();

        $unfinished = User::factory()->create();
        $token = $this->token($unfinished);
        $this->getJson('/api/v1/me', $this->auth($token))->assertOk(); // reading is fine
        $this->postJson('/api/v1/ads', $this->carAd(), $this->auth($token))->assertForbidden();
        $this->assertSame(0, Ad::count());
    }

    public function test_my_ads_edit_sold_delete_only_my_own(): void
    {
        app(FeatureManager::class)->set('ads.approval', false); // with approval ON an edit goes back to "pending"
        $token = $this->token();
        $mine = Ad::factory()->live()->create(['user_id' => $this->seller->id, 'category_id' => Category::where('slug', 'cars')->value('id'), 'city_id' => $this->mogadishu->id]);
        $other = Ad::factory()->live()->create(['category_id' => Category::where('slug', 'cars')->value('id'), 'city_id' => $this->mogadishu->id]);

        $this->getJson('/api/v1/me/ads', $this->auth($token))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id)->assertJsonPath('meta.total', 1);

        // Someone else's ad is "not found" for every action.
        $this->putJson("/api/v1/ads/{$other->id}", $this->carAd(), $this->auth($token))->assertNotFound();
        $this->postJson("/api/v1/ads/{$other->id}/sold", [], $this->auth($token))->assertNotFound();
        $this->deleteJson("/api/v1/ads/{$other->id}", [], $this->auth($token))->assertNotFound();
        $this->assertSame(AdStatus::Live, $other->fresh()?->status);

        $this->putJson("/api/v1/ads/{$mine->id}", $this->carAd(['title' => 'Toyota Vitz 2012 new title']), $this->auth($token))
            ->assertOk()->assertJsonPath('data.title', 'Toyota Vitz 2012 new title');

        $this->postJson("/api/v1/ads/{$mine->id}/sold", [], $this->auth($token))->assertOk()->assertJsonPath('data.status', 'sold');
        $this->postJson("/api/v1/ads/{$mine->id}/sold", [], $this->auth($token))->assertStatus(409);
        $this->putJson("/api/v1/ads/{$mine->id}", $this->carAd(['title' => 'Cannot change sold']), $this->auth($token))->assertStatus(409);

        $this->deleteJson("/api/v1/ads/{$mine->id}", [], $this->auth($token))->assertOk();
        $this->assertSoftDeleted($mine);
    }

    public function test_photo_upload_status_and_attach(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Queue::fake();
        $token = $this->token();

        $id = $this->post('/api/v1/photos', ['photo' => UploadedFile::fake()->image('car.jpg', 1200, 900)], $this->auth($token) + ['Accept' => 'application/json'])
            ->assertCreated()->json('id');
        $this->getJson("/api/v1/photos/{$id}", $this->auth($token))->assertOk()->assertJsonPath('status', 'processing');

        // Another user cannot see it or use it.
        $otherToken = $this->token(User::factory()->completed()->create());
        $this->getJson("/api/v1/photos/{$id}", $this->auth($otherToken))->assertNotFound();
        $otherAd = $this->postJson('/api/v1/ads', $this->carAd(['photos' => [$id]]), $this->auth($otherToken))->assertCreated()->json('data.id');
        $this->assertNull(AdPhoto::findOrFail($id)->ad_id);
        $this->assertNotNull($otherAd);

        $adId = $this->postJson('/api/v1/ads', $this->carAd(['title' => 'Toyota Vitz with photo', 'photos' => [$id]]), $this->auth($token))
            ->assertCreated()->assertJsonPath('data.photo_ids', [$id])->json('data.id');
        $this->assertSame($adId, AdPhoto::findOrFail($id)->ad_id);
    }

    public function test_no_session_or_cookie_from_the_api(): void
    {
        $res = $this->getJson('/api/v1/me', $this->auth($this->token()))->assertOk();
        $this->assertEmpty($res->headers->getCookies());
    }

    public function test_account_page_makes_shows_once_and_deletes_keys(): void
    {
        $this->actingAs($this->seller)->get('/account/api')->assertOk()->assertSee(__('account.api.none'));

        $res = $this->actingAs($this->seller)->post('/account/api', ['name' => 'My phone', 'write' => '1'])->assertOk();
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
        preg_match('/iib_\d+_[A-Za-z0-9]{40}/', (string) $res->getContent(), $m);
        $this->assertNotEmpty($m);
        $this->getJson('/api/v1/me', $this->auth($m[0]))->assertOk();
        $this->assertSame(['read', 'write'], ApiToken::firstOrFail()->abilities);

        // Shown once: the list page never has it again.
        $this->actingAs($this->seller)->get('/account/api')->assertOk()->assertSee('My phone')->assertDontSee($m[0]);

        // Someone else cannot delete my key.
        $token = ApiToken::firstOrFail();
        $this->actingAs(User::factory()->completed()->create())->delete("/account/api/{$token->id}")->assertNotFound();
        $this->actingAs($this->seller)->delete("/account/api/{$token->id}")->assertRedirect();
        $this->assertSame(0, ApiToken::count());
        $this->getJson('/api/v1/me', $this->auth($m[0]))->assertUnauthorized();
    }

    public function test_at_most_five_keys_and_read_only_by_default(): void
    {
        for ($i = 0; $i < ApiToken::MAX_PER_USER; $i++) {
            $this->actingAs($this->seller)->post('/account/api', ['name' => "Key {$i}"])->assertOk();
        }
        $this->assertSame(['read'], ApiToken::firstOrFail()->abilities);
        $this->actingAs($this->seller)->post('/account/api', ['name' => 'One more'])->assertSessionHasErrors('name');
        $this->assertSame(ApiToken::MAX_PER_USER, ApiToken::count());
    }

    public function test_guest_cannot_open_the_key_page(): void
    {
        $this->get('/account/api')->assertRedirect(url('/login'));
    }
}
