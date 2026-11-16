<?php

namespace Tests;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Redis/database caches live outside the test transaction, so start every test clean.
        // (Database cache: the table may not exist yet on a brand-new test database.)
        try {
            Cache::flush();
        } catch (QueryException) {
            // Nothing cached yet.
        }

        // Pages render without a built front-end (npm run build) in tests.
        $this->withoutVite();
    }
}
