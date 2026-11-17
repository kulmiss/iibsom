<?php

namespace Tests;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    /**
     * Safety net: RefreshDatabase empties the database, so it must be a test
     * database (name ends in _testing), never a dev or live one.
     */
    protected function setUpTraits()
    {
        if (isset(class_uses_recursive(static::class)[RefreshDatabase::class])) {
            $this->refuseRealDatabase();
        }

        return parent::setUpTraits();
    }

    private function refuseRealDatabase(): void
    {
        $name = (string) config('database.connections.'.config('database.default').'.database');
        if (! str_ends_with($name, '_testing')) {
            throw new \RuntimeException("Tests refuse to empty the database [{$name}]: its name must end in _testing.");
        }
    }
}
