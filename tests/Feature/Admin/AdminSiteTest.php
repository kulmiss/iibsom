<?php

namespace Tests\Feature\Admin;

use App\Models\Ad;
use App\Models\Banner;
use App\Models\Category;
use App\Models\Page;
use App\Models\Report;
use App\Models\User;
use App\Support\Features\FeatureManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AdminSiteTest extends AdminTestCase
{
    public function test_core_pages_exist_and_show_on_the_site(): void
    {
        foreach (['about', 'safety', 'terms', 'privacy'] as $slug) {
            $this->get('/'.$slug)->assertOk();
        }
        $this->get('/safety')->assertSee('Never send money in advance');
        $this->get('/')->assertSee('href="'.url('/safety').'"', false);
    }

    public function test_edit_page_and_html_is_escaped(): void
    {
        $this->asStaff();
        $about = Page::where('slug', 'about')->first();

        $this->putJson("/admin/api/pages/{$about->id}", ['title_en' => 'About us', 'body_en' => "Hello **world**\n\n<script>alert(1)</script>", 'enabled' => true])->assertOk();

        $this->get('/about')->assertSee('<strong>world</strong>', false)->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_page_switched_off_is_404(): void
    {
        $this->asStaff();
        $terms = Page::where('slug', 'terms')->first();
        $this->putJson("/admin/api/pages/{$terms->id}", ['title_en' => 'Terms', 'body_en' => 'x', 'enabled' => false])->assertOk();

        $this->get('/terms')->assertNotFound();
    }

    public function test_page_preview(): void
    {
        $this->asStaff();
        $this->postJson('/admin/api/pages/preview', ['body' => '# Hi <b>x</b>'])->assertOk()->assertJsonPath('html', fn ($h) => str_contains($h, '<h1>Hi &lt;b&gt;x&lt;/b&gt;</h1>'));
    }

    public function test_page_seeder_never_overwrites_edits(): void
    {
        Page::where('slug', 'about')->first()->update(['body' => ['en' => 'My own text']]);
        $this->seed();

        $this->assertSame('My own text', Page::where('slug', 'about')->first()->body['en']);
    }

    public function test_banner_upload_shows_on_home(): void
    {
        Storage::fake('public');
        config(['iibsom.photos_disk' => 'public']);
        $this->asStaff();

        $this->post('/admin/api/banners', [
            'title_en' => 'Sell your car today', 'link_url' => '/post', 'image' => UploadedFile::fake()->image('b.jpg', 1600, 500),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('live', true);

        $banner = Banner::first();
        Storage::disk('public')->assertExists($banner->image['wide']);
        $this->get('/')->assertSee('alt="Sell your car today"', false)->assertSee('href="/post"', false);
    }

    public function test_banner_link_must_be_safe(): void
    {
        Storage::fake('public');
        $this->asStaff();

        foreach (['javascript:alert(1)', 'http://evil.test', 'data:text/html,x'] as $bad) {
            $this->post('/admin/api/banners', ['title_en' => 'X', 'link_url' => $bad, 'image' => UploadedFile::fake()->image('b.jpg', 1600, 500)], ['Accept' => 'application/json'])
                ->assertUnprocessable();
        }
    }

    public function test_banners_switch_off_hides_them(): void
    {
        Storage::fake('public');
        config(['iibsom.photos_disk' => 'public']);
        $this->asStaff();
        $this->post('/admin/api/banners', ['title_en' => 'Promo', 'image' => UploadedFile::fake()->image('b.jpg', 1600, 500)], ['Accept' => 'application/json']);

        app(FeatureManager::class)->set('site.banners', false);
        $this->get('/')->assertDontSee('alt="Promo"', false);
    }

    public function test_add_staff_gets_one_time_password_and_must_set_up_2fa(): void
    {
        $this->asStaff();

        $res = $this->postJson('/admin/api/staff', ['name' => 'Ayaan', 'email' => 'Ayaan@iibsom.com', 'role' => 'moderator'])->assertCreated()->json();
        $this->assertSame(16, strlen($res['temporary_password']));
        $this->assertFalse($res['two_factor']);

        $this->postJson('/admin/api/logout');
        $this->postJson('/admin/api/login', ['email' => 'ayaan@iibsom.com', 'password' => $res['temporary_password']])->assertJson(['next' => 'setup']);
    }

    public function test_cannot_demote_self_or_last_super_admin(): void
    {
        $me = $this->asStaff();

        $this->putJson("/admin/api/staff/{$me->id}", ['role' => 'support'])->assertStatus(422);
        $this->deleteJson("/admin/api/staff/{$me->id}")->assertStatus(422);
    }

    public function test_change_role_reset_2fa_and_remove(): void
    {
        $this->asStaff();
        $mod = $this->staff('moderator');

        $this->putJson("/admin/api/staff/{$mod->id}", ['role' => 'support'])->assertOk()->assertJsonPath('role', 'support');
        $this->postJson("/admin/api/staff/{$mod->id}/reset-2fa")->assertOk()->assertJsonPath('two_factor', false);
        $this->postJson("/admin/api/staff/{$mod->id}/reset-password")->assertOk()->assertJsonStructure(['temporary_password']);
        $this->deleteJson("/admin/api/staff/{$mod->id}")->assertOk();

        $this->assertSame('customer', $mod->fresh()->role);
        $this->assertNull($mod->fresh()->password);
    }

    public function test_moderator_cannot_manage_staff_or_site(): void
    {
        $this->asStaff('moderator');

        $this->getJson('/admin/api/staff')->assertForbidden();
        $this->getJson('/admin/api/pages')->assertForbidden();
        $this->getJson('/admin/api/banners')->assertForbidden();
        $this->getJson('/admin/api/health')->assertForbidden();
        $this->postJson('/admin/api/demo/delete', ['confirm' => 'DELETE'])->assertForbidden();
    }

    public function test_health_page(): void
    {
        Storage::fake('public');
        $this->asStaff();

        $this->getJson('/admin/api/health')->assertOk()
            ->assertJsonFragment(['key' => 'database', 'status' => 'ok'])
            ->assertJsonFragment(['label' => 'Hosting mode']);
    }

    public function test_delete_all_demo_data(): void
    {
        $this->asStaff();
        $demoUser = User::factory()->completed()->create();
        $demoUser->forceFill(['is_demo' => true])->save();
        $demoAd = Ad::factory()->live()->create(['user_id' => $demoUser->id]);
        $demoAd->forceFill(['is_demo' => true])->save();
        $realAd = Ad::factory()->live()->create();
        $report = new Report(['reason' => 'scam']);
        $report->ad_id = $demoAd->id;
        $report->user_id = $demoUser->id;
        $report->is_demo = true;
        $report->save();

        $this->getJson('/admin/api/demo')->assertJsonPath('counts.ads', 1)->assertJsonPath('counts.users', 1);
        $this->postJson('/admin/api/demo/delete', ['confirm' => 'yes'])->assertUnprocessable();
        $this->postJson('/admin/api/demo/delete', ['confirm' => 'DELETE'])->assertOk()->assertJsonPath('deleted.ads', 1);

        $this->assertNull(Ad::withTrashed()->find($demoAd->id));
        $this->assertNull(User::find($demoUser->id));
        $this->assertNotNull($realAd->fresh());
        $this->assertSame(0, Report::count());
    }

    public function test_showing_demo_data_updates_home_counts(): void
    {
        $this->asStaff();
        $cars = Category::where('slug', 'cars')->first();
        $demo = Ad::factory()->live()->create(['category_id' => $cars->id]);
        $demo->forceFill(['is_demo' => true])->save();
        $this->assertSame(0, $cars->fresh()->ads_count);

        $this->putJson('/admin/api/features/site.demo_data', ['enabled' => true])->assertOk();
        $this->assertSame(1, $cars->fresh()->ads_count);
    }
}
