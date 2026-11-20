<?php

namespace Tests\Feature;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\AdPhoto;
use App\Models\Category;
use App\Models\City;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Share\ShareImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** PR 110: the picture shown when an ad is shared on WhatsApp, Facebook or Telegram. */
class ShareImageTest extends TestCase
{
    use RefreshDatabase;

    private Ad $ad;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
        Storage::fake('public');
        $seller = User::factory()->completed()->create(['name' => 'Seller Secret', 'phone' => '+252615123456']);
        $this->ad = Ad::factory()->live()->create([
            'user_id' => $seller->id,
            'title' => 'Toyota Vitz 2012 automatic, very clean, call 0615123456',
            'price' => 6500,
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);
    }

    private function shareUrl(): string
    {
        $html = (string) $this->get($this->ad->url())->assertOk()->getContent();
        preg_match('/<meta property="og:image" content="([^"]+)"/', $html, $m);
        $this->assertNotEmpty($m[1] ?? null, 'no og:image');

        return html_entity_decode($m[1]);
    }

    public function test_ad_page_has_open_graph_and_twitter_tags_with_the_share_picture(): void
    {
        $html = (string) $this->get($this->ad->url())->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<meta property="og:image" content="'.preg_quote(url('/share/'.$this->ad->id), '#').'-[a-f0-9]{12}-en\.jpg">#', $html);
        foreach (['og:image:width" content="1200"', 'og:image:height" content="630"', 'og:image:type" content="image/jpeg"', 'og:site_name" content="iibsom"', 'og:url" content="'.$this->ad->url().'"', 'name="twitter:card" content="summary_large_image"', 'name="twitter:image"', 'og:image:alt'] as $tag) {
            $this->assertStringContainsString($tag, $html);
        }
        $this->assertStringContainsString('https://t.me/share/url?url='.rawurlencode($this->ad->url()), $html);
        $this->assertStringContainsString('https://wa.me/?text=', $html);
    }

    public function test_the_picture_is_a_1200_by_630_jpeg_cached_and_without_session_or_cookie(): void
    {
        $url = $this->shareUrl();
        $res = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertStringContainsString('immutable', (string) $res->headers->get('Cache-Control'));
        $this->assertSame([], $res->headers->getCookies());

        $size = getimagesizefromstring((string) $res->getContent());
        $this->assertSame([1200, 630], [$size[0] ?? 0, $size[1] ?? 0]);

        $files = Storage::disk('local')->files(ShareImage::FOLDER);
        $this->assertCount(1, $files);
        $before = Storage::disk('local')->lastModified($files[0]);
        $this->travel(5)->minutes();
        $this->get($url)->assertOk();
        $this->assertSame($before, Storage::disk('local')->lastModified($files[0])); // served from the saved file
    }

    public function test_no_phone_number_or_seller_name_is_written_on_it(): void
    {
        $words = json_encode(ShareImage::texts($this->ad->load('photos', 'city'))) ?: '';
        $this->assertStringNotContainsString('0615123456', $words);
        $this->assertStringNotContainsString('615123456', str_replace(' ', '', $words));
        $this->assertStringNotContainsString('Seller Secret', $words);
        $this->assertStringContainsString('$6,500', $words);
        $this->assertStringContainsString('Mogadishu', $words);
        $this->assertSame('Toyota Vitz 2012 automatic, very clean, call', ShareImage::withoutPhoneNumbers($this->ad->title));
        $this->assertSame('Price 6500, 150000 km', ShareImage::withoutPhoneNumbers('Price 6500, 150000 km')); // other numbers stay
    }

    public function test_long_titles_wrap_to_three_lines_and_end_with_dots(): void
    {
        $lines = ShareImage::lines(str_repeat('Wonderful ', 30), 38, 504, 3);
        $this->assertCount(3, $lines);
        $this->assertStringEndsWith('…', $lines[2]);
        $this->assertSame(['Short title'], ShareImage::lines('Short   title', 38, 504, 3));
        $this->assertCount(1, ShareImage::lines(str_repeat('X', 200), 38, 504, 3)); // one very long word is cut
    }

    public function test_changing_the_ad_makes_a_new_picture_and_old_addresses_follow(): void
    {
        $old = $this->shareUrl();
        $this->get($old)->assertOk();

        $this->ad->forceFill(['price' => 5900])->save();
        $new = $this->shareUrl();
        $this->assertNotSame($old, $new);

        $this->get($old)->assertRedirect($new)->assertStatus(301);
        $this->get($new)->assertOk();
        $this->assertCount(1, Storage::disk('local')->files(ShareImage::FOLDER)); // the old file is gone
    }

    public function test_with_a_cover_photo_and_without(): void
    {
        $photo = AdPhoto::factory()->create(['ad_id' => $this->ad->id, 'user_id' => $this->ad->user_id]);
        $path = $photo->variants['medium']['webp'] ?? null;
        if ($path) {
            $img = imagecreatetruecolor(900, 600);
            ob_start();
            imagewebp($img);
            Storage::disk($photo->disk)->put($path, (string) ob_get_clean());
        }
        $this->get($this->shareUrl())->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_only_live_ads_and_the_switch_off_falls_back_to_the_photo(): void
    {
        $url = $this->shareUrl();
        $this->ad->forceFill(['status' => AdStatus::Sold])->save();
        $this->get($url)->assertNotFound();

        $this->ad->forceFill(['status' => AdStatus::Live])->save();
        app(FeatureManager::class)->set('share.image', false);
        $this->get($this->ad->url())->assertOk()->assertDontSee(url('/share/'), false);
        $this->get($url)->assertNotFound();

        $this->get('/share/'.$this->ad->id.'-000000000000-xx.jpg')->assertNotFound();
    }

    public function test_prune_removes_pictures_of_ads_that_are_not_live(): void
    {
        $this->get($this->shareUrl())->assertOk();
        $this->assertSame(0, ShareImage::prune());
        $this->ad->forceFill(['status' => AdStatus::Expired])->save();
        $this->assertSame(1, ShareImage::prune());
        $this->assertSame([], Storage::disk('local')->files(ShareImage::FOLDER));
    }
}
