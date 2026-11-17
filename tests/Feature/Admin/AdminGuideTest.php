<?php

namespace Tests\Feature\Admin;

use App\Support\Features\FeatureManager;
use App\Support\Help\AdminGuide;
use Illuminate\Support\Facades\File;

class AdminGuideTest extends AdminTestCase
{
    public function test_every_staff_role_can_read_the_guide(): void
    {
        foreach (['super_admin', 'moderator', 'support'] as $role) {
            $this->asStaff($role);
            $res = $this->getJson('/admin/api/guide')->assertOk()->assertJsonPath('sections.0.slug', 'start');
            $this->assertCount(18, $res->json('sections'));
            $this->assertStringContainsString('<li>', $res->json('sections.0.html'));
        }
        $this->getJson('/admin/api/me')->assertOk()->assertJsonPath('user.guide', true);
    }

    public function test_guests_and_customers_cannot_read_it(): void
    {
        $this->getJson('/admin/api/guide')->assertUnauthorized();
        $this->get('/admin/api/guide/print')->assertStatus(401);
        $this->get('/admin/api/guide/pdf')->assertStatus(401);
    }

    public function test_every_question_mark_button_opens_a_real_section(): void
    {
        $slugs = array_column(AdminGuide::sections(), 'slug');
        $used = [];
        foreach (File::allFiles(base_path('admin/src/features')) as $file) {
            preg_match_all("/(?:help|section)='([a-z-]+)'/", $file->getContents(), $m);
            $used = array_merge($used, $m[1]);
        }

        $this->assertNotEmpty($used);
        $this->assertSame([], array_values(array_diff(array_unique($used), $slugs)), 'A "?" button points to a guide section that does not exist.');
    }

    public function test_every_admin_page_has_a_question_mark_button(): void
    {
        foreach (File::allFiles(base_path('admin/src/features')) as $file) {
            $code = $file->getContents();
            if (str_contains($file->getPathname(), '/guide/')) {
                continue; // the guide page itself
            }
            $pages = preg_match_all('/<Page\b/', $code);
            $this->assertSame($pages, preg_match_all("/\bhelp='[a-z-]+'/", $code), $file->getRelativePathname().' has a page without a "?" button (help=...).');
        }
    }

    public function test_print_page_has_every_section(): void
    {
        $this->asStaff('support');

        $res = $this->get('/admin/api/guide/print')->assertOk()
            ->assertSee('<meta name="guide-version" content="'.AdminGuide::version().'">', false)
            ->assertSee('noindex', false);
        foreach (AdminGuide::sections() as $section) {
            $res->assertSee('id="'.$section['slug'].'"', false)->assertSee($section['title']);
        }
    }

    public function test_pdf_is_made_from_the_current_text(): void
    {
        $this->assertFileExists(resource_path(AdminGuide::PDF));
        $this->assertSame(
            AdminGuide::version(),
            trim((string) file_get_contents(resource_path('help/admin-guide.version'))),
            'The guide text changed: make the PDF again (tools/make-guide-pdf).',
        );

        $this->asStaff('moderator');
        $res = $this->get('/admin/api/guide/pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('iibsom-admin-guide.pdf', (string) $res->headers->get('Content-Disposition'));
    }

    public function test_html_in_guide_files_is_never_run(): void
    {
        foreach (AdminGuide::sections() as $section) {
            $this->assertStringNotContainsString('<script', $section['html']);
            $this->assertNotSame('', $section['title']);
        }
    }

    public function test_switch_off_hides_the_guide(): void
    {
        app(FeatureManager::class)->set('help.admin_guide', false);
        $this->asStaff();

        $this->getJson('/admin/api/guide')->assertNotFound();
        $this->get('/admin/api/guide/print')->assertNotFound();
        $this->get('/admin/api/guide/pdf')->assertNotFound();
        $this->getJson('/admin/api/me')->assertOk()->assertJsonPath('user.guide', false);
    }
}
