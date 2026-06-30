<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use ReflectionClass;
use RoundlyConsulting\Advertisements\AdvertisementsServiceProvider;
use RoundlyConsulting\Geolocation\GeolocationServiceProvider;
use RoundlyConsulting\MediaLibrary\MediaLibraryServiceProvider;

abstract class TestCase extends Orchestra
{
    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            MediaLibraryServiceProvider::class,
            GeolocationServiceProvider::class,
            AdvertisementsServiceProvider::class,
        ];
    }

    /**
     * Config overrides applied before the package provider boots, so subclasses
     * can exercise config-gated behaviour (e.g. the facade alias opt-out).
     *
     * @return array<string, mixed>
     */
    protected function packageConfig(): array
    {
        return [];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // Media-library: store on a fakeable public disk, use the GD driver, and keep the
        // responsive ladder small so creative variant generation stays fast under test.
        $app['config']->set('media.disk', 'public');
        $app['config']->set('media.image_driver', 'gd');
        $app['config']->set('media.responsive.widths', [320, 640]);

        foreach ($this->packageConfig() as $key => $value) {
            $app['config']->set($key, $value);
        }
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Media-library ships the `media` table the creative buckets persist into.
        $mediaPackage = dirname((string) (new ReflectionClass(MediaLibraryServiceProvider::class))->getFileName(), 2);
        $this->loadMigrationsFrom($mediaPackage.'/database/migrations');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
        });
    }
}
