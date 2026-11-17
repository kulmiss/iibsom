<?php

namespace Tests\Feature\Admin;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\Shop;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

class ShopsTest extends AdminTestCase
{
    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('iibsom.photos_disk'));
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->seller = User::factory()->completed()->create(['name' => 'Abdi Motors Owner']);
    }

    private function on(): void
    {
        app(FeatureManager::class)->set('shops', true);
    }

    private function save(array $extra = [], ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->seller)->post('/account/shop', $extra + [
            'name' => 'Abdi Motors',
            'slug' => 'abdi-motors',
            'description' => 'Used cars from Japan.',
            'hours' => 'Sat–Thu 8:00–20:00',
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
            'logo' => UploadedFile::fake()->image('logo.png', 500, 500),
        ]);
    }

    private function car(array $attributes = []): Ad
    {
        return Ad::factory()->live()->create($attributes + [
            'user_id' => $this->seller->id,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);
    }

    public function test_off_by_default(): void
    {
        $this->assertFalse(feature('shops'));
        $this->actingAs($this->seller)->get('/account/shop')->assertNotFound();
        $this->save()->assertNotFound();
        $this->get('/shop/abdi-motors')->assertNotFound();
        $this->asStaff();
        $this->getJson('/admin/api/shops')->assertNotFound();
        $this->getJson('/admin/api/me')->assertJsonPath('user.shops', false);
    }

    public function test_seller_makes_a_shop_but_it_is_hidden_until_turned_on(): void
    {
        $this->on();
        $this->actingAs($this->seller)->get('/account/shop')->assertOk()->assertSee('My shop');
        $this->actingAs($this->seller)->get('/so/account/shop')->assertOk()->assertSee('Dukaankayga');

        $this->save()->assertRedirect(url('/account/shop'))->assertSessionHas('status', 'Shop saved.');

        $shop = Shop::firstOrFail();
        $this->assertSame('abdi-motors', $shop->slug);
        $this->assertNotNull($shop->logoUrl('large'));
        Storage::disk((string) config('iibsom.photos_disk'))->assertExists($shop->logo['small'] ?? '');
        $this->actingAs($this->seller)->get('/account/shop')->assertSee('Your shop page is not public yet.');
        auth()->logout();
        $this->get('/shop/abdi-motors')->assertNotFound();
    }

    public function test_bad_shop_details_are_refused(): void
    {
        $this->on();
        $this->save(user: User::factory()->completed()->create());

        $this->save(['slug' => 'abdi-motors'])->assertSessionHasErrors(['slug' => 'Another shop already uses this web address. Try another one.']);
        $this->save(['slug' => 'Abdi Motors!'])->assertSessionHasErrors('slug');
        $this->save(['slug' => 'ab'])->assertSessionHasErrors('slug');
        $this->save(['city_id' => City::where('slug', 'kismayo')->value('id')])->assertSessionHasErrors('city_id'); // city OFF
        $this->save(['logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('logo');
        $this->assertSame(1, Shop::count());
    }

    public function test_admin_turns_it_on_and_the_page_shows_the_shop_and_its_ads(): void
    {
        $this->on();
        $this->save(['description' => '<b>Used</b> cars from Japan.']);
        $live = $this->car(['title' => 'Toyota Vitz 2012 shop car']);
        $this->car(['title' => 'Demo car hidden', 'is_demo' => true]);
        app(FeatureManager::class)->set('site.demo_data', false);
        $shop = Shop::firstOrFail();

        $this->asStaff();
        $this->getJson('/admin/api/shops')->assertOk()->assertJsonPath('data.0.slug', 'abdi-motors')->assertJsonPath('data.0.active', false);
        $this->postJson("/admin/api/shops/{$shop->id}/activate", ['months' => 1])->assertOk()
            ->assertJsonPath('active_until', now('Africa/Mogadishu')->addMonthNoOverflow()->toDateString());
        auth()->logout();

        $page = $this->get('/shop/abdi-motors')->assertOk()
            ->assertSee('<h1', false)->assertSee('Abdi Motors')->assertSee('Sat–Thu 8:00–20:00')
            ->assertSee('Used cars from Japan.')->assertDontSee('<b>Used</b>', false)
            ->assertSee('Toyota Vitz 2012 shop car')->assertDontSee('Demo car hidden');
        $this->assertSame([], $page->headers->getCookies());
        $this->get($live->url())->assertOk()->assertSee('href="'.url('/shop/abdi-motors').'"', false)->assertSee('Visit shop');
        $this->get('/so/shop/abdi-motors')->assertOk()->assertSee('Xayeysiisyada dukaankan');
        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/shop/abdi-motors'), false);
    }

    public function test_more_months_add_to_the_end_date_and_turn_off_hides_it(): void
    {
        $this->on();
        $this->save();
        $shop = Shop::firstOrFail();
        $this->asStaff();

        $this->postJson("/admin/api/shops/{$shop->id}/activate", ['months' => 1]);
        $this->postJson("/admin/api/shops/{$shop->id}/activate", ['months' => 3])->assertOk()
            ->assertJsonPath('active_until', now('Africa/Mogadishu')->addMonthNoOverflow()->addMonthsNoOverflow(3)->toDateString());
        $this->postJson("/admin/api/shops/{$shop->id}/activate", ['months' => 13])->assertUnprocessable();

        $this->postJson("/admin/api/shops/{$shop->id}/activate", ['months' => 0])->assertOk()->assertJsonPath('active_until', null);
        $this->assertDatabaseHas('admin_activities', ['action' => 'shop.activated']);
        auth()->logout();
        $this->get('/shop/abdi-motors')->assertNotFound();
    }

    public function test_ended_or_banned_shops_are_hidden(): void
    {
        $this->on();
        $this->save();
        auth()->logout();
        $shop = Shop::firstOrFail();
        $shop->forceFill(['active_until' => now('Africa/Mogadishu')->subDay()->toDateString()])->save();
        $this->get('/shop/abdi-motors')->assertNotFound();

        $shop->forceFill(['active_until' => now('Africa/Mogadishu')->toDateString()])->save();
        $this->get('/shop/abdi-motors')->assertOk(); // last day still counts

        // Banned by staff: the cached page must go at once.
        $this->get('/shop/abdi-motors')->assertHeader('X-Page-Cache', 'HIT');
        $this->asStaff('moderator');
        $this->postJson("/admin/api/users/{$this->seller->id}/ban", ['reason' => 'Scam', 'hide_ads' => false, 'block_phone' => false])->assertOk();
        auth()->logout();
        $this->get('/shop/abdi-motors')->assertNotFound();
        $this->get('/sitemap.xml')->assertDontSee('/shop/abdi-motors', false);
    }

    public function test_only_the_super_admin_manages_shops(): void
    {
        $this->on();
        $this->save();
        $shop = Shop::firstOrFail();

        foreach (['moderator', 'support'] as $role) {
            $this->asStaff($role);
            $this->getJson('/admin/api/shops')->assertForbidden();
            $this->postJson("/admin/api/shops/{$shop->id}/activate", ['months' => 1])->assertForbidden();
        }
        $this->assertNull($shop->fresh()?->active_until);
    }

    public function test_new_logo_replaces_the_old_files(): void
    {
        $this->on();
        $this->save();
        $old = Shop::firstOrFail()->logo;

        $this->save(['logo' => UploadedFile::fake()->image('new.png', 300, 300)]);

        $disk = Storage::disk((string) config('iibsom.photos_disk'));
        $disk->assertMissing($old['large'] ?? '');
        $disk->assertExists(Shop::firstOrFail()->logo['large'] ?? '');
    }
}
