<?php

namespace App\Providers;

use App\Support\Features\FeatureManager;
use App\Support\Health\HealthChecker;
use App\Support\Settings\SettingsManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // "scoped" = fresh per request / per queue job, so a long-running
        // worker never keeps an old switch value in memory.
        $this->app->scoped(FeatureManager::class);
        $this->app->scoped(SettingsManager::class);
        $this->app->singleton(HealthChecker::class);
    }

    public function boot(): void
    {
        // @feature('contact.whatsapp') ... @endfeature
        Blade::if('feature', fn (string $key) => app(FeatureManager::class)->enabled($key));
    }
}
