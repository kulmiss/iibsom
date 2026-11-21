<?php

namespace Tests\Feature;

use App\Http\Middleware\CompactHtml;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Support\Features\FeatureManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/** PR 112: pages without Blade's indentation; nothing that shows changes. */
class CompactHtmlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_pages_are_smaller_and_keep_line_breaks_scripts_and_text_boxes(): void
    {
        $this->freezeTime(); // "posted 1 second ago" must be the same on both pages
        $ad = Ad::factory()->live()->create([
            'title' => 'Toyota Vitz',
            'description' => "Line one\n    indented line two\n\n\nline five",
            'category_id' => Category::where('slug', 'cars')->value('id'),
            'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);

        $small = (string) $this->get($ad->url())->assertOk()->getContent();
        app(FeatureManager::class)->set('performance.compact_html', false);
        $big = (string) $this->get($ad->url())->assertOk()->getContent();

        $this->assertLessThan(strlen($big) * 0.9, strlen($small));
        $this->assertDoesNotMatchRegularExpression('/\n[ \t]+</', $small);
        $this->assertStringContainsString("Line one\n", $small); // the description still has its line breaks
        // Same text on screen: only whitespace differs.
        $this->assertSame(preg_replace('/\s+/', ' ', strip_tags($big)), preg_replace('/\s+/', ' ', strip_tags($small)));
        // JSON-LD and other scripts are untouched.
        $scripts = fn (string $h) => preg_match_all('#<script\b.*?</script>#is', (string) preg_replace('/nonce="[^"]*"/', '', $h), $m) ? $m[0] : [];
        $this->assertSame($scripts($big), $scripts($small)); // (the nonce changes on every page)
    }

    public function test_text_boxes_pre_and_scripts_are_copied_exactly(): void
    {
        $html = "<div>\n        <p>Hi</p>\n\n\n    <textarea>Hello\n    keep these spaces\n\n</textarea>\n  <pre>  a\n    b</pre>\n <script>\n    var x = 1;\n</script>\n</div>";
        $request = Request::create('/x');
        $response = (new CompactHtml)->handle($request, fn () => response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']));

        $this->assertSame("<div>\n<p>Hi</p>\n<textarea>Hello\n    keep these spaces\n\n</textarea>\n<pre>  a\n    b</pre>\n<script>\n    var x = 1;\n</script>\n</div>", $response->getContent());

        $json = (new CompactHtml)->handle($request, fn () => response()->json(['a' => "x\n    y"]));
        $this->assertSame(json_encode(['a' => "x\n    y"]), $json->getContent());
    }

    public function test_not_for_json_or_files(): void
    {
        $this->getJson('/api/v1/categories')->assertOk()->assertHeader('Content-Type', 'application/json');
    }
}
