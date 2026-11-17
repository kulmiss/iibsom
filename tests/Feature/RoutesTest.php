<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoutesTest extends TestCase
{
    /** `php artisan route:cache` (used on the live site) fails when two routes share a name. */
    public function test_route_names_are_unique(): void
    {
        $names = collect(Route::getRoutes()->getRoutes())->map->getName()->filter();

        $this->assertSame([], $names->duplicates()->values()->all());
    }
}
