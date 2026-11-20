<?php

namespace App\Providers;

use App\Models\Area;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\City;
use App\Models\Region;
use App\Search\AdSearch;
use App\Search\DatabaseAdSearch;
use App\Session\PrivateDatabaseSessionHandler;
use App\Support\Catalog;
use App\Support\Features\FeatureManager;
use App\Support\Health\HealthChecker;
use App\Support\Settings\SettingsManager;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;
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
        // Sessions in the database never keep the IP of visitors who are not signed in.
        Session::extend('database', fn ($app) => new PrivateDatabaseSessionHandler(
            $app['db']->connection($app['config']['session.connection']),
            $app['config']['session.table'],
            $app['config']['session.lifetime'],
            $app,
        ));

        // @feature('contact.whatsapp') ... @endfeature
        Blade::if('feature', fn (string $key) => app(FeatureManager::class)->enabled($key));

        // Any change to categories or locations clears the cached lists.
        foreach ([Category::class, CategoryField::class, City::class, Region::class, Area::class] as $model) {
            $model::saved(fn () => app(Catalog::class)->flush());
            $model::deleted(fn () => app(Catalog::class)->flush());
        }

        // /up (for outside uptime monitors, PR 94): answers 500 when the database is down. No details are shown.
        Event::listen(DiagnosingHealth::class, function () {
            if (app(HealthChecker::class)->only('database')['status'] !== 'ok') {
                throw new \RuntimeException('Database not reachable.');
            }
        });
    }
}
