<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Redis/database caches live outside the test transaction, so start every test clean.
        Cache::flush();

        // Pages render without a built front-end (npm run build) in tests.
        $this->withoutVite();
    }
}
