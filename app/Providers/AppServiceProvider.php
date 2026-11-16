<?php

namespace App\Providers;

use App\Models\Area;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\City;
use App\Models\Region;
use App\Search\AdSearch;
use App\Search\DatabaseAdSearch;
use App\Support\Catalog;
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
        $this->app->singleton(Catalog::class);
        $this->app->bind(AdSearch::class, DatabaseAdSearch::class);
    }

    public function boot(): void
    {
        // @feature('contact.whatsapp') ... @endfeature
        Blade::if('feature', fn (string $key) => app(FeatureManager::class)->enabled($key));

        // Any change to categories or locations clears the cached lists.
        foreach ([Category::class, CategoryField::class, City::class, Region::class, Area::class] as $model) {
            $model::saved(fn () => app(Catalog::class)->flush());
            $model::deleted(fn () => app(Catalog::class)->flush());
        }
    }
}
