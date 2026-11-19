<?php

namespace Tests\Feature\Admin;

use App\Enums\AdStatus;
use App\Models\Ad;
use App\Models\Category;
use App\Models\City;
use App\Models\ContactMessage;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Safety\GuestReports;
use App\Support\Settings\SettingsManager;
use Illuminate\Console\Scheduling\Schedule;

class HelpAndContactTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
    }

    private function form(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Amina', 'email' => 'Amina@Example.com', 'topic' => 'account',
            'message' => 'I cannot sign in with Google on my phone. Please help.',
            't' => GuestReports::token(now()->subSeconds(30)->getTimestamp()),
        ];
    }

    // ---- Help articles ----

    public function test_posting_rules_and_how_to_buy_in_both_languages(): void
    {
        $this->get('/help')->assertOk()->assertSee('Posting rules')->assertSee('How to buy on iibsom');
        $this->get('/help/posting-rules')->assertOk()->assertSee('Not allowed')->assertSee('Why an ad is not approved');
        $this->get('/so/help/posting-rules')->assertOk()->assertSee('Xeerarka dhigista xayeysiiska');
        $this->get('/help/how-to-buy')->assertOk()->assertSee('Meet and check')->assertSee('href="'.url('/safety').'"', false);
        $this->get('/so/help/how-to-buy')->assertOk()->assertSee('Sida wax looga iibsado iibsom');

        app(FeatureManager::class)->set('help.posting_rules', false);
        app(FeatureManager::class)->set('help.how_to_buy', false);
        $this->get('/help/posting-rules')->assertNotFound();
        $this->get('/help/how-to-buy')->assertNotFound();
        $this->get('/help')->assertOk()->assertDontSee('Posting rules');
    }

    public function test_not_approved_ads_and_the_posting_page_link_to_the_rules(): void
    {
        $seller = User::factory()->completed()->create();
        Ad::factory()->create([
            'user_id' => $seller->id, 'status' => AdStatus::Rejected, 'rejection_reason' => 'Wrong category',
            'category_id' => Category::where('slug', 'cars')->value('id'), 'city_id' => City::where('slug', 'mogadishu')->value('id'),
        ]);

        $this->actingAs($seller)->get('/account/ads?tab=rejected')->assertOk()->assertSee('href="'.url('/help/posting-rules').'"', false)->assertSee('Read our posting rules');
        $this->actingAs($seller)->get('/post')->assertOk()->assertSee('href="'.url('/help/posting-rules').'"', false);
    }

    // ---- Contact form ----

    public function test_a_message_is_saved_without_any_visitor_data(): void
    {
        $page = $this->get('/contact')->assertOk()->assertSee('Contact us')->assertSee('name="website"', false);
        $this->assertEmpty($page->headers->getCookies(), 'the page is cookie-free (cached for guests)');
        $this->get('/so/contact')->assertOk()->assertSee('Nala soo xiriir');

        $res = $this->post('/contact', $this->form())->assertRedirect(url('/contact?sent=thanks'));
        $this->assertEmpty($res->headers->getCookies(), 'sending sets no cookie');
        $m = ContactMessage::sole();
        $this->assertSame('amina@example.com', $m->email);
        $this->assertSame('account', $m->topic);
        $this->assertSame('en', $m->locale);
        $this->assertSame(['id', 'name', 'email', 'topic', 'message', 'locale', 'handled_at', 'handled_by', 'created_at'], array_keys($m->fresh()->getAttributes()), 'no IP or user agent column');

        $this->get('/contact?sent=thanks')->assertOk()->assertSee('Thank you. We got your message');

        // The same message again the same day is kept once.
        $this->post('/contact', $this->form())->assertRedirect();
        $this->assertSame(1, ContactMessage::count());
    }

    public function test_mistakes_show_the_form_again_with_what_was_typed(): void
    {
        $res = $this->post('/contact', $this->form(['email' => 'not-an-email', 'message' => 'short']))->assertOk();
        $res->assertSee('Please check the boxes marked in red.')->assertSee('value="Amina"', false)->assertSee('>short</textarea>', false)
            ->assertSee('aria-invalid="true"', false);
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
        $this->assertSame(0, ContactMessage::count());

        $this->post('/contact', $this->form(['topic' => 'hack']))->assertOk()->assertSee('Please check the boxes marked in red.');
        $this->post('/contact', $this->form(['message' => '<script>alert(1)</script>'.str_repeat('x', 20)]))->assertRedirect();
        $this->assertStringNotContainsString('<script>', (string) ContactMessage::sole()->message);
    }

    public function test_robots_are_dropped_quietly(): void
    {
        $this->post('/contact', $this->form(['website' => 'http://spam.example']))->assertRedirect(url('/contact?sent=thanks'));
        $this->post('/contact', $this->form(['t' => GuestReports::token()]))->assertRedirect(url('/contact?sent=thanks')); // too fast
        $this->post('/contact', $this->form(['t' => 'made-up']))->assertRedirect(url('/contact?sent=thanks'));
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_switch_off(): void
    {
        app(FeatureManager::class)->set('site.contact_form', false);
        $this->get('/contact')->assertNotFound();
        $this->post('/contact', $this->form())->assertNotFound();
    }

    // ---- Admin ----

    public function test_support_reads_and_marks_messages_done_moderators_cannot(): void
    {
        $this->post('/contact', $this->form())->assertRedirect();
        $id = ContactMessage::sole()->id;

        $this->asStaff('moderator');
        $this->getJson('/admin/api/messages')->assertForbidden();

        $this->asStaff('support');
        $this->getJson('/admin/api/messages')->assertOk()->assertJsonPath('open', 1)
            ->assertJsonPath('data.0.topic', 'My account or signing in')->assertJsonPath('data.0.email', 'amina@example.com');
        $this->postJson("/admin/api/messages/{$id}/done")->assertOk();
        $this->getJson('/admin/api/messages')->assertJsonPath('open', 0)->assertJsonCount(0, 'data');
        $this->getJson('/admin/api/messages?show=done')->assertJsonCount(1, 'data')->assertJsonPath('data.0.handled_by', fn ($n) => is_string($n));
        $this->assertDatabaseHas('admin_activities', ['action' => 'contact.done']);
        $this->postJson("/admin/api/messages/{$id}/reopen")->assertOk();
        $this->assertNull(ContactMessage::sole()->handled_at);
    }

    public function test_old_messages_are_deleted_every_night(): void
    {
        ContactMessage::create(['name' => 'Old', 'email' => 'a@b.so', 'topic' => 'other', 'message' => 'old message text', 'locale' => 'en', 'created_at' => now()->subDays(181)]);
        ContactMessage::create(['name' => 'New', 'email' => 'a@b.so', 'topic' => 'other', 'message' => 'new message text', 'locale' => 'en', 'created_at' => now()]);

        collect(app(Schedule::class)->events())->first(fn ($e) => $e->description === 'prune-contact-messages')->run($this->app);

        $this->assertSame(['New'], ContactMessage::pluck('name')->all());
    }
}
