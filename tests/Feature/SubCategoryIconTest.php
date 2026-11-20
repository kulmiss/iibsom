<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Support\Features\FeatureManager;
use Illuminate\Foundation\Vite;
use Tests\Feature\Admin\AdminTestCase;

/** PR 98: sub-category icons in the home side panels, like the main categories, light for the page. */
class SubCategoryIconTest extends AdminTestCase
{
    /** The home page HTML of the Cars row in the Vehicles panel. */
    private function carsRow(): string
    {
        $html = (string) $this->get('/?nocache=1')->assertOk()->getContent();
        preg_match('#<li><a href="'.preg_quote(url('/cars'), '#').'"[^>]*>(.*?)</a></li>#s', $html, $m);
        $this->assertNotEmpty($m, 'Cars row not found in the side panel');

        return $m[1];
    }

    private function fakeBuild(): void
    {
        $this->swap(Vite::class, new class extends Vite
        {
            public function asset($asset, $buildDirectory = null): string
            {
                return url('/build/assets/'.basename($asset, '.svg').'-AbC123.svg');
            }
        });
    }

    public function test_icon_comes_from_the_build_and_is_drawn_only_by_css(): void
    {
        $this->fakeBuild();
        $row = $this->carsRow();

        $this->assertMatchesRegularExpression('#<span class="cat-thumb has-i"\s+style="--i:url\(/build/assets/car-AbC123\.svg\)"\s+data-thumb></span>#', $row); // short address: no host
        $this->assertStringNotContainsString('<svg', $row); // not drawn into the page (141 rows made it heavy)
        $this->assertStringNotContainsString('<img', $row); // never a broken image
    }

    public function test_first_letter_when_the_icon_is_missing(): void
    {
        $this->fakeBuild();
        Category::where('slug', 'cars')->firstOrFail()->forceFill(['icon' => 'no-such-icon'])->save();

        $this->assertMatchesRegularExpression('#<span class="cat-thumb"\s+data-thumb>C</span>#', $this->carsRow());
    }

    public function test_switch_off_shows_names_only(): void
    {
        $this->fakeBuild();
        $this->assertTrue(config('features')['home.sub_icons']['default']);
        app(FeatureManager::class)->set('home.sub_icons', false);

        $row = $this->carsRow();
        $this->assertStringNotContainsString('cat-thumb', $row);
        $this->assertStringContainsString('Cars', $row);
    }

    public function test_the_switch_is_on_the_admin_home_page_list(): void
    {
        $this->asStaff();
        $this->getJson('/admin/api/home-page')->assertOk()->assertJsonFragment(['key' => 'home.sub_icons']);
    }

    public function test_icon_picker_offers_only_icons_the_admin_can_draw(): void
    {
        // Found in the browser check (PR 98): our own social logos were offered and the admin's icon set has none of them (console error).
        $this->asStaff();
        $icons = $this->getJson('/admin/api/categories')->assertOk()->json('icons');
        $this->assertContains('car', $icons);
        $this->assertEmpty(array_filter($icons, fn ($n) => str_starts_with($n, 'social-')));
    }
}
