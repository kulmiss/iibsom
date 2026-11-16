<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Turns every feature switch OFF (one at a time, then all at once) and opens
 * the main pages as a guest, a seller and the API. Nothing may crash (5xx),
 * and pages that should exist still answer 200.
 */
class SwitchCrawlTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private Ad $ad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);

        $this->seller = User::factory()->completed()->create(['city_id' => City::where('slug', 'mogadishu')->value('id')]);
        $this->ad = Ad::factory()->live()->create([
            'user_id' => $this->seller->id,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);
    }

    /** @return list<string> */
    private function guestUrls(): array
    {
        $path = parse_url($this->ad->url(), PHP_URL_PATH);

        return ['/', '/so', '/cars', '/vehicles', '/cars/mogadishu', '/search?q=toyota', $path, '/so'.$path, '/safety', '/terms',
            '/login', '/help', '/so/help/post-an-ad', '/sitemap.xml', '/robots.txt', '/for-you?c[]=1', "/ads/{$this->ad->id}/contact", '/api/v1/ads', '/api/v1/categories'];
    }

    /** @return list<string> */
    private function sellerUrls(): array
    {
        return ['/', '/account', '/account/profile', '/account/saved', '/account/ads', '/account/ads?tab=ended', "/account/ads/{$this->ad->id}/edit", '/post', '/post/cars'];
    }

    private function crawl(string $label): void
    {
        foreach ($this->guestUrls() as $url) {
            $status = $this->get($url)->getStatusCode();
            $this->assertLessThan(500, $status, "{$label}: guest {$url} crashed ({$status})");
        }
        foreach ($this->sellerUrls() as $url) {
            $status = $this->actingAs($this->seller)->get($url)->getStatusCode();
            $this->assertLessThan(500, $status, "{$label}: seller {$url} crashed ({$status})");
        }
        auth()->logout();
    }

    public function test_every_switch_off_one_by_one(): void
    {
        $features = app(FeatureManager::class);

        foreach (array_keys(config('features')) as $key) {
            $features->set($key, ! config("features.{$key}.default"));
            $this->crawl("{$key} flipped");
            $features->reset($key);
        }

        // Core pages still work with all defaults.
        $this->get('/')->assertOk();
        $this->get($this->ad->url())->assertOk();
    }

    public function test_everything_off_and_everything_on(): void
    {
        $features = app(FeatureManager::class);

        foreach (array_keys(config('features')) as $key) {
            $features->set($key, false);
        }
        $this->crawl('all OFF');
        $this->get('/')->assertOk();
        $this->get('/post')->assertRedirect(); // guests → sign in, or 404 when off; never a crash

        foreach (array_keys(config('features')) as $key) {
            $features->set($key, true);
        }
        $this->crawl('all ON');
        $this->get('/')->assertOk();
    }
}
