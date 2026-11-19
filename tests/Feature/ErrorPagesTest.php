<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Friendly error pages in the visitor's language (found by the checklist run: a guest saw a bare English "429"). */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(SettingsManager::class)->set('languages.enabled', ['en', 'so']);
        config(['app.debug' => false]);
        Route::middleware('web')->get('/_error/{lang}/{code}', function (string $lang, int $code) {
            app()->setLocale($lang);
            abort($code);
        });
        Route::middleware('web')->get('/_boom', fn () => throw new \RuntimeException('boom'));
    }

    public function test_each_page_in_english_and_somali(): void
    {
        $pages = [
            403 => ['You cannot open this page', 'Ma furi kartid boggan'],
            419 => ['This page was open too long', 'Boggan wuu furnaa muddo dheer'],
            429 => ['Too many tries', 'Isku day aad u badan'],
            500 => ['Something went wrong', 'Wax baa khaldamay'],
            503 => ['iibsom is being updated', 'iibsom waa la cusboonaysiinayaa'],
        ];
        foreach ($pages as $code => [$en, $so]) {
            app()->setLocale('en');
            $this->get("/_error/en/{$code}")->assertStatus($code)->assertSee($en)->assertSee('Back to home')->assertSee('href="'.url('/').'"', false);
            $this->get("/_error/so/{$code}")->assertStatus($code)->assertSee($so)->assertSee('Ku noqo bogga hore')->assertSee('href="'.url('/so').'"', false)->assertSee('lang="so"', false);
        }
    }

    public function test_a_real_crash_shows_the_friendly_page_and_no_details(): void
    {
        $html = (string) $this->get('/_boom')->assertStatus(500)->assertSee('Something went wrong')->getContent();
        $this->assertStringNotContainsString('RuntimeException', $html, 'No error details for visitors.');
        $this->assertStringNotContainsString('boom', $html);
    }

    public function test_too_many_reports_from_one_guest_shows_the_page_in_somali(): void
    {
        $ad = Ad::factory()->live()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post("/so/ads/{$ad->id}/report-guest", ['reason' => 'scam'])->assertRedirect();
        }
        app()->setLocale('en'); // a new request starts in English; only the address says Somali
        $this->post("/so/ads/{$ad->id}/report-guest", ['reason' => 'scam'])->assertStatus(429)->assertSee('Isku day aad u badan')->assertSee('lang="so"', false);
    }
}
