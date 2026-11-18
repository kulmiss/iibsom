<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\City;
use App\Models\Conversation;
use App\Models\User;
use App\Support\Cache\PageCache;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldsViewsOffersTest extends TestCase
{
    use RefreshDatabase;

    private Ad $ad;

    private User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $this->seller = User::factory()->completed()->create();
        $this->ad = Ad::factory()->live()->create([
            'user_id' => $this->seller->id, 'title' => 'Toyota Hilux 2016 offer test', 'price' => 25000,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
            'fields' => ['brand' => 'toyota', 'body_type' => 'pickup'],
        ]);
    }

    // ---- Body type and RAM ----

    public function test_cars_have_body_type_and_phones_have_ram_in_both_languages(): void
    {
        $body = CategoryField::where('key', 'body_type')->firstOrFail();
        $this->assertSame('cars', $body->category->slug);
        $this->assertTrue($body->filterable);
        $this->assertSame('Bikab', collect($body->options)->firstWhere('value', 'pickup')['label']['so']);

        $ram = CategoryField::where('key', 'ram')->whereHas('category', fn ($q) => $q->where('slug', 'mobile-phones'))->firstOrFail();
        $this->assertSame(['2-gb', '3-gb', '4-gb', '6-gb', '8-gb', '12-gb'], collect($ram->options)->pluck('value')->all());
    }

    public function test_body_type_filters_cars(): void
    {
        Ad::factory()->live()->create([
            'title' => 'Toyota Vitz hatch', 'category_id' => $this->ad->category_id, 'city_id' => $this->ad->city_id,
            'fields' => ['brand' => 'toyota', 'body_type' => 'hatchback'],
        ]);

        $this->get('/cars?f[body_type]=pickup')->assertOk()->assertSee('Toyota Hilux 2016 offer test')->assertDontSee('Toyota Vitz hatch');
        $this->get('/cars')->assertOk()->assertSee('Body type');
        $this->get($this->ad->url())->assertOk()->assertSee('Pickup');
        $this->get('/so/cars')->assertOk()->assertSee('Nooca jirka');
    }

    public function test_upgrade_adds_the_fields_once_and_keeps_admin_changes(): void
    {
        $body = CategoryField::where('key', 'body_type')->firstOrFail();
        $body->forceFill(['label' => ['en' => 'Shape', 'so' => 'Qaab']])->save();
        CategoryField::where('key', 'ram')->whereHas('category', fn ($q) => $q->where('slug', 'mobile-phones'))->delete();
        $count = CategoryField::count();

        (new CategorySeeder)->addMissing();

        $this->assertSame($count + 1, CategoryField::count()); // RAM back, nothing doubled
        $this->assertSame('Shape', $body->fresh()->label['en']);
    }

    // ---- Number of views ----

    public function test_views_show_to_everyone_only_with_both_switches(): void
    {
        $this->ad->forceFill(['views' => 1234])->save();
        app(FeatureManager::class)->set('ad_stats', true);

        $this->get($this->ad->url())->assertOk()->assertSee('1,234 views');
        $this->get('/so'.parse_url($this->ad->url(), PHP_URL_PATH))->assertOk()->assertSee('1,234 jeer la daawaday');

        app(FeatureManager::class)->set('ads.public_views', false);
        PageCache::bump();
        $this->get($this->ad->url())->assertOk()->assertDontSee('1,234 views');

        app(FeatureManager::class)->set('ads.public_views', true);
        app(FeatureManager::class)->set('ad_stats', false);
        $this->get($this->ad->url())->assertOk()->assertDontSee('1,234 views');
    }

    public function test_no_views_line_for_an_ad_nobody_opened(): void
    {
        app(FeatureManager::class)->set('ad_stats', true);
        $this->get($this->ad->url())->assertOk()->assertDontSee(' views</span>', false);
    }

    // ---- Make an offer ----

    public function test_an_offer_opens_the_chat_with_the_text_written_in_but_not_sent(): void
    {
        app(FeatureManager::class)->set('chat', true);
        $buyer = User::factory()->completed()->create();

        $this->actingAs($buyer)->get($this->ad->url())->assertOk()->assertSee('Make an offer')->assertSee('name="offer"', false);
        $res = $this->actingAs($buyer)->post("/ads/{$this->ad->id}/chat", ['offer' => '22500'])->assertRedirect();
        $c = Conversation::firstOrFail();
        $this->assertSame(url("/messages/{$c->id}?offer=22500"), $res->headers->get('Location'));

        $this->actingAs($buyer)->get("/messages/{$c->id}?offer=22500")->assertOk()
            ->assertSee('name="body" rows="3"', false) // room to read the written-in offer without JavaScript
            ->assertSee('>Hello, I would like to offer $22,500 for this. Is that OK for you?</textarea>', false);
        $this->actingAs($buyer)->get("/so/messages/{$c->id}?offer=22500")->assertOk()->assertSee('waxaan doonayaa inaan ku bixiyo $22,500');
        $this->assertSame(0, $c->messages()->count(), 'Nothing is sent until the buyer taps Send.');
    }

    public function test_bad_offers_are_refused_before_a_chat_is_made(): void
    {
        app(FeatureManager::class)->set('chat', true);
        $buyer = User::factory()->completed()->create();

        foreach (['0', '-5', 'abc', '1.5', '100000000', ''] as $bad) {
            $this->actingAs($buyer)->from($this->ad->url())->post("/ads/{$this->ad->id}/chat", ['offer' => $bad])
                ->assertRedirect($this->ad->url())->assertSessionHasErrors('offer');
        }
        $this->assertSame(0, Conversation::count());

        // A made-up address shows no offer text.
        $c = Conversation::create(['ad_id' => $this->ad->id, 'buyer_id' => $buyer->id, 'seller_id' => $this->seller->id]);
        foreach (['0', '123456789', '<b>5</b>', '5e3'] as $bad) {
            $this->actingAs($buyer)->get("/messages/{$c->id}?offer=".urlencode($bad))->assertOk()->assertDontSee('I would like to offer');
        }
    }

    public function test_offers_need_both_switches_and_never_on_your_own_ad(): void
    {
        app(FeatureManager::class)->set('chat', true);
        $this->actingAs($this->seller)->post("/ads/{$this->ad->id}/chat", ['offer' => '100'])->assertForbidden();

        app(FeatureManager::class)->set('chat.offers', false);
        $buyer = User::factory()->completed()->create();
        $this->actingAs($buyer)->get($this->ad->url())->assertOk()->assertDontSee('Make an offer');
        $this->actingAs($buyer)->post("/ads/{$this->ad->id}/chat", ['offer' => '100'])->assertRedirect();
        $c = Conversation::firstOrFail();
        $this->actingAs($buyer)->get("/messages/{$c->id}?offer=100")->assertOk()->assertDontSee('I would like to offer');

        app(FeatureManager::class)->set('chat', false);
        $this->actingAs($buyer)->get($this->ad->url())->assertOk()->assertDontSee('Make an offer');
    }
}
