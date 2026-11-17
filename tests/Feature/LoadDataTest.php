<?php

namespace Tests\Feature;

use App\Models\Ad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoadDataTest extends TestCase
{
    use RefreshDatabase;

    /** The load-test filler deletes ads and users: it must refuse every database not named *_load. */
    public function test_refuses_any_database_not_named_load(): void
    {
        $this->seed();
        Ad::factory()->create();

        $this->artisan('iibsom:load-data', ['--ads' => 10, '--users' => 2, '--force' => true])
            ->expectsOutputToContain('Refused')
            ->assertFailed();
        $this->assertSame(1, Ad::count());
    }
}
