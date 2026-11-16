<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_returns_ok(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_home_page_loads_with_site_name(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Iibsom')
            ->assertSee('Buy and sell anything in Somalia');
    }

    public function test_tests_run_on_mysql_like_production(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('iibsom_testing', config('database.connections.mysql.database'));
    }
}
