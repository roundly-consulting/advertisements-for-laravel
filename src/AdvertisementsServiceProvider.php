<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Advertisements\Contracts\CreativeRenderer;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Support\CreativeResolver;

final class AdvertisementsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/advertisements.php', 'advertisements');

        $this->app->singleton(AdvertisementManager::class);
        $this->app->bind(CreativeRenderer::class, CreativeResolver::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'advertisements');

        $this->registerFacadeAlias();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/advertisements.php' => config_path('advertisements.php'),
            ], 'advertisements-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'advertisements-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/advertisements'),
            ], 'advertisements-views');
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
