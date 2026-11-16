<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AppConfigTest extends TestCase
{
    public function test_env_example_uses_port_8095_and_mysql(): void
    {
        $env = file_get_contents(__DIR__.'/../../.env.example');

        $this->assertStringContainsString('APP_NAME=iibsom', $env);
        $this->assertStringContainsString('APP_URL=http://localhost:8095', $env);
        $this->assertStringContainsString('DB_CONNECTION=mysql', $env);
    }

    public function test_env_example_contains_no_app_key(): void
    {
        $env = file_get_contents(__DIR__.'/../../.env.example');

        $this->assertMatchesRegularExpression('/^APP_KEY=$/m', $env);
    }
}
