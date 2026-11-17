<?php

namespace Tests\Feature\Admin;

use App\Models\Ad;
use App\Models\Banner;
use App\Models\Category;
use App\Models\City;
use App\Support\Catalog;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

class BannerAdsTest extends AdminTestCase
{
    private const BROWSER = 'Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Chrome/120 Mobile Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['iibsom.photos_disk' => 'public']);
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
    }

    private function on(): void
    {
        app(FeatureManager::class)->set('banner_ads', true);
    }

    private function create(array $fields): TestResponse
    {
        return $this->post('/admin/api/banners', $fields + [
            'title_en' => 'Hodan Motors: cars on credit',
            'link_url' => 'https://hodanmotors.example',
            'image' => UploadedFile::fake()->image('b.jpg', 1200, 300),
        ], ['Accept' => 'application/json']);
    }

    public function test_paid_places_need_the_switch(): void
    {
        $this->asStaff();
        $this->getJson('/admin/api/banners/options')->assertOk()->assertJsonPath('banner_ads', false);

        $this->create(['placement' => 'list'])->assertUnprocessable()->assertJsonPath('errors.placement.0', 'Turn on the "Banner ads" switch to use paid places.');
        $this->create([])->assertCreated()->assertJsonPath('placement', 'slider');
    }

    public function test_paid_banner_needs_a_link_and_slider_banners_are_never_aimed(): void
    {
        $this->on();
        $this->asStaff();
        $cars = Category::where('slug', 'cars')->value('id');

        $this->create(['placement' => 'list', 'link_url' => ''])->assertUnprocessable()->assertJsonPath('errors.link_url.0', 'A paid banner needs a link.');
        $this->create(['placement' => 'slider', 'category_id' => $cars])->assertCreated()->assertJsonPath('category_id', null);
        $this->create(['placement' => 'list', 'category_id' => $cars, 'advertiser' => '<b>Hodan</b>'])->assertCreated()
            ->assertJsonPath('category_id', $cars)->assertJsonPath('advertiser', 'Hodan');
    }

    public function test_list_banner_shows_only_where_it_is_aimed(): void
    {
        $this->on();
        $this->asStaff();
        $vehicles = Category::where('slug', 'vehicles')->value('id');
        $mogadishu = City::where('slug', 'mogadishu')->value('id');
        $baidoa = City::where('slug', 'baidoa')->firstOrFail();
        $baidoa->forceFill(['enabled' => true])->save();
        $baidoa->region->forceFill(['enabled' => true])->save();
        app(Catalog::class)->flush();
        $this->create(['placement' => 'list', 'category_id' => $vehicles, 'city_id' => $mogadishu])->assertCreated();
        $banner = Banner::firstOrFail();
        auth()->logout();

        // Sub category of Vehicles, in Mogadishu: shown, with the Ad label and a counted link.
        $this->get('/cars/mogadishu')->assertOk()
            ->assertSee('href="'.url("/b/{$banner->id}").'"', false)->assertSee('rel="sponsored noopener"', false)->assertSee('alt="Hodan Motors: cars on credit"', false);
        $this->get('/so/cars/mogadishu')->assertOk()->assertSee('Xayeysiis');
        // Another city or category: not shown.
        $this->get('/cars/baidoa')->assertOk()->assertDontSee("/b/{$banner->id}", false);
        $this->get('/phones-tablets')->assertOk()->assertDontSee("/b/{$banner->id}", false);
        // Not on the home slider or ad pages.
        $this->get('/')->assertOk()->assertDontSee("/b/{$banner->id}", false);
    }

    public function test_home_and_ad_page_slots(): void
    {
        $this->on();
        $this->asStaff();
        $this->create(['placement' => 'home', 'title_en' => 'Home slot banner'])->assertCreated();
        $this->create(['placement' => 'ad', 'title_en' => 'Ad page banner'])->assertCreated();
        auth()->logout();
        $ad = Ad::factory()->live()->create(['category_id' => Category::where('slug', 'cars')->value('id'), 'city_id' => City::where('slug', 'mogadishu')->value('id')]);

        $this->get('/')->assertOk()->assertSee('alt="Home slot banner"', false)->assertDontSee('alt="Ad page banner"', false);
        $this->get($ad->url())->assertOk()->assertSee('alt="Ad page banner"', false)->assertDontSee('alt="Home slot banner"', false);
    }

    public function test_clicks_are_counted_per_day_without_visitor_data(): void
    {
        $this->on();
        $this->asStaff();
        $this->create(['placement' => 'list'])->assertCreated();
        $banner = Banner::firstOrFail();
        auth()->logout();

        $res = $this->withHeader('User-Agent', self::BROWSER)->get("/b/{$banner->id}")->assertRedirect('https://hodanmotors.example');
        $this->assertSame([], $res->headers->getCookies());
        $this->withHeader('User-Agent', self::BROWSER)->get("/b/{$banner->id}");
        $this->withHeader('User-Agent', 'Googlebot/2.1')->get("/b/{$banner->id}")->assertRedirect('https://hodanmotors.example');

        $this->assertSame(2, $banner->fresh()?->clicks);
        $this->assertSame(['banner_id', 'day', 'clicks'], array_keys((array) DB::table('banner_clicks')->first()));
        $this->assertSame(2, (int) DB::table('banner_clicks')->value('clicks'));

        $this->asStaff();
        $this->getJson('/admin/api/banners')->assertJsonPath('0.clicks', 2)->assertJsonPath('0.clicks_30', 2);
    }

    public function test_ended_off_or_switched_off_banners_are_not_shown_or_counted(): void
    {
        $this->on();
        $this->asStaff();
        $this->create(['placement' => 'list', 'ends_at' => now()->subDay()->toDateString(), 'starts_at' => now()->subDays(5)->toDateString()])->assertCreated();
        $ended = Banner::firstOrFail();
        auth()->logout();

        $this->get('/cars')->assertDontSee("/b/{$ended->id}", false);
        $this->withHeader('User-Agent', self::BROWSER)->get("/b/{$ended->id}")->assertRedirect(); // still goes on
        $this->assertSame(0, $ended->fresh()?->clicks);

        $ended->forceFill(['ends_at' => null, 'starts_at' => null])->save();
        $this->get('/cars')->assertSee("/b/{$ended->id}", false);
        app(FeatureManager::class)->set('banner_ads', false);
        $this->get('/cars')->assertDontSee("/b/{$ended->id}", false);
        $this->withHeader('User-Agent', self::BROWSER)->get("/b/{$ended->id}")->assertNotFound();
    }

    public function test_unknown_banner_or_no_link_is_404(): void
    {
        $this->get('/b/999999')->assertNotFound();
        $banner = Banner::create(['title' => ['en' => 'x'], 'enabled' => true]);
        $this->get("/b/{$banner->id}")->assertNotFound();
    }
}
