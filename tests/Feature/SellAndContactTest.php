<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\Conversation;
use App\Models\User;
use App\Support\Cache\PageCache;
use App\Support\Features\FeatureManager;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellAndContactTest extends TestCase
{
    use RefreshDatabase;

    private Ad $ad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        $seller = User::factory()->completed()->create(['phone' => '+252611234567']);
        $this->ad = Ad::factory()->live()->create([
            'user_id' => $seller->id, 'title' => 'Toyota Vitz 2012 contact test',
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);
    }

    public function test_how_to_sell_article_and_links(): void
    {
        $this->get('/help/how-to-sell')->assertOk()->assertSee('How to sell fast on iibsom')
            ->assertSee('href="'.url('/help/post-an-ad').'"', false)->assertSee('href="'.url('/safety').'"', false);
        $this->get('/so/help/how-to-sell')->assertOk()->assertSee('Sida dhakhso ugu iibiso iibsom')->assertSee('href="'.url('/so/help/post-an-ad').'"', false);
        $this->get('/help')->assertOk()->assertSeeInOrder(['How to sell fast on iibsom', 'How to post an ad']);
        $this->get('/')->assertOk()->assertSee('href="'.url('/help/how-to-sell').'" class="chip', false);
        $this->actingAs(User::factory()->completed()->create())->get('/post')->assertOk()->assertSee('6 tips to sell fast');

        app(FeatureManager::class)->set('help.how_to_sell', false);
        $this->get('/help/how-to-sell')->assertNotFound();
        $this->get('/help')->assertOk()->assertDontSee('How to sell fast');
        $this->get('/so')->assertOk()->assertSee('href="'.url('/so/post').'" class="chip', false);
    }

    public function test_safety_tip_comes_with_the_number(): void
    {
        $this->get($this->ad->url())->assertOk()
            ->assertSee('data-safety-tip', false)->assertSee('Never send money before.');
        app(FeatureManager::class)->set('contact.safety_tip', false);
        PageCache::bump();
        $this->get($this->ad->url())->assertOk()->assertDontSee('data-safety-tip', false);
    }

    public function test_quick_questions_fill_the_message_box(): void
    {
        app(FeatureManager::class)->set('chat', true);
        $buyer = User::factory()->completed()->create();
        $this->actingAs($buyer)->get($this->ad->url())->assertOk()->assertSee('Ask the seller')->assertSee('name="quick" value="available"', false);

        $res = $this->actingAs($buyer)->post("/ads/{$this->ad->id}/chat", ['quick' => 'available'])->assertRedirect();
        $c = Conversation::firstOrFail();
        $this->assertSame(url("/messages/{$c->id}?say=available"), $res->headers->get('Location'));
        $this->actingAs($buyer)->get("/messages/{$c->id}?say=available")->assertOk()->assertSee('>Is it still available?</textarea>', false);
        $this->assertSame(0, $c->messages()->count(), 'Nothing is sent until the buyer taps Send.');

        // Somali, and unknown values are ignored.
        $this->actingAs($buyer)->get("/so/messages/{$c->id}?say=price")->assertOk()->assertSee('Waa imisa qiimahaaga ugu dambeeya?');
        $this->actingAs($buyer)->get("/messages/{$c->id}?say=<script>")->assertOk()->assertDontSee('&lt;script&gt;</textarea>', false);
        $this->actingAs($buyer)->post("/ads/{$this->ad->id}/chat", ['quick' => 'hack'])->assertRedirect(url("/messages/{$c->id}"));

        app(FeatureManager::class)->set('chat.quick_replies', false);
        $this->actingAs($buyer)->get($this->ad->url())->assertOk()->assertDontSee('Ask the seller');
        $this->actingAs($buyer)->post("/ads/{$this->ad->id}/chat", ['quick' => 'available'])->assertRedirect(url("/messages/{$c->id}"));
    }
}
