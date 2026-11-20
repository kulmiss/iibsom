<?php

namespace Tests\Feature\Admin;

use App\Models\Ad;
use App\Models\User;
use App\Support\Features\FeatureManager;

/** PR 102: the admin's global search — finds ads, people, categories, switches and pages; each group by permission. */
class AdminSearchTest extends AdminTestCase
{
    private function search(string $q): array
    {
        return $this->getJson('/admin/api/search?q='.urlencode($q))->assertOk()->json('groups');
    }

    public function test_super_admin_finds_everything_in_groups(): void
    {
        $this->asStaff();
        $ad = Ad::factory()->live()->create(['title' => 'Toyota Hilux Zebra 2016']);
        $person = User::factory()->completed()->create(['name' => 'Asha Zebra', 'email' => 'asha@example.com', 'phone' => '+252615550101']);

        $groups = $this->search('zebra');
        $this->assertSame($ad->id, $groups['ads'][0]['id']);
        $this->assertStringContainsString('#'.$ad->id, $groups['ads'][0]['hint']);
        $this->assertSame($person->id, $groups['users'][0]['id']);

        $this->assertSame($ad->id, $this->search('#'.$ad->id)['ads'][0]['id']); // by number
        $this->assertSame($person->id, $this->search('asha@example')['users'][0]['id']);
        $this->assertSame($person->id, $this->search('0615550101')['users'][0]['id']); // phone, any way it is typed

        $this->assertSame('Cars', $this->search('cars')['categories'][0]['title']);
        $this->assertSame('Cars', $this->search('baabuur')['categories'][0]['title']); // Somali name
        $this->assertContains('Watchdog', array_column($this->search('watchdog')['switches'], 'title'));
        $this->assertNotEmpty($this->search('terms')['pages']);
    }

    public function test_each_group_only_for_staff_who_may_open_it(): void
    {
        $this->asStaff('support'); // ads.view + users.view only
        Ad::factory()->live()->create(['title' => 'Cars Zebra']);
        $groups = $this->search('cars');
        $this->assertArrayHasKey('ads', $groups);
        $this->assertArrayNotHasKey('categories', $groups);
        $this->assertArrayNotHasKey('switches', $groups);
        $this->assertArrayNotHasKey('pages', $groups);
    }

    public function test_fast_and_safe_input(): void
    {
        $this->getJson('/admin/api/search?q=zz')->assertUnauthorized();
        $this->asStaff();
        $this->getJson('/admin/api/search?q=a')->assertUnprocessable(); // 2 letters at least
        Ad::factory()->count(8)->live()->create(['title' => 'Same Zebra title']);
        $this->assertCount(5, $this->search('zebra')['ads']); // 5 per group
        $this->assertArrayNotHasKey('ads', $this->search('%%')); // % is a letter, not "anything"
    }

    public function test_demo_data_is_left_out_while_it_is_hidden(): void
    {
        $this->asStaff();
        Ad::factory()->live()->create(['title' => 'Demo Zebra', 'is_demo' => true]);
        $this->setDemo(false);
        $this->assertArrayNotHasKey('ads', $this->search('zebra'));
    }

    private function setDemo(bool $on): void
    {
        app(FeatureManager::class)->set('site.demo_data', $on);
    }
}
