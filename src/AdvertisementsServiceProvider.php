<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Advertisements\Facades\Advertisements;

final class AdvertisementsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/advertisements.php', 'advertisements');

        $this->app->singleton(AdvertisementManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerFacadeAlias();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/advertisements.php' => config_path('advertisements.php'),
            ], 'advertisements-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'advertisements-migrations');
        }
    }

    private function registerFacadeAlias(): void
    {
        if (config('advertisements.register_facade_alias') !== true) {
            return;
        }

        AliasLoader::getInstance()->alias('Advertisements', Advertisements::class);
    }
}
