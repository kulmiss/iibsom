<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Conversation;
use App\Models\User;
use App\Support\Features\FeatureManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PR 84 bug fix: pages with their own script (ad page, post an ad, my ads, chat) carry
 * <meta name="page-script">, so the instant language switch loads them fully instead of
 * swapping the HTML (a swap left their buttons dead: "Show number", photo viewer…).
 */
class PageScriptReloadTest extends TestCase
{
    use RefreshDatabase;

    private const META = '<meta name="page-script"';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_pages_with_their_own_script_ask_for_a_full_load(): void
    {
        $ad = Ad::factory()->live()->create();
        $this->get($ad->url())->assertOk()->assertSee(self::META.' content="ad-page">', false);

        $this->actingAs($ad->user);
        $this->get(lroute('post'))->assertOk()->assertSee(self::META.' content="post-ad">', false);
        $this->get(lroute('post.form', ['category' => 'cars']))->assertOk()->assertSee(self::META.' content="post-ad">', false);
        $this->get(lroute('account.ads'))->assertOk()->assertSee(self::META.' content="confirm">', false);

        app(FeatureManager::class)->set('chat', true);
        $buyer = User::factory()->completed()->create();
        $c = Conversation::create(['ad_id' => $ad->id, 'buyer_id' => $buyer->id, 'seller_id' => $ad->user_id]);
        $this->actingAs($buyer)->get(route('chat.show', $c))->assertOk()->assertSee(self::META.' content="chat">', false);
    }

    public function test_plain_pages_keep_the_instant_swap(): void
    {
        Ad::factory()->live()->create();
        foreach (['/', '/cars', '/search?q=car', '/categories'] as $url) {
            $this->get($url)->assertOk()->assertDontSee(self::META, false);
        }
    }

    public function test_the_switch_script_reads_the_marker(): void
    {
        $js = (string) file_get_contents(resource_path('js/language-switch.js'));
        $this->assertSame(2, substr_count($js, 'meta[name="page-script"]'), 'checked on the current page and on the fetched page');
    }
}
