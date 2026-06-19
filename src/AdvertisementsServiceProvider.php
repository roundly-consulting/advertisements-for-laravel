<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements;

use Illuminate\Support\ServiceProvider;

final class AdvertisementsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/advertisements.php', 'advertisements');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/advertisements.php' => config_path('advertisements.php'),
            ], 'advertisements-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'advertisements-migrations');
        }
    }
}
