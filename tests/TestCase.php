<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use RoundlyConsulting\Advertisements\AdvertisementsServiceProvider;

abstract class TestCase extends Orchestra
{
    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
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

        foreach ($this->packageConfig() as $key => $value) {
            $app['config']->set($key, $value);
        }
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
        });
    }
}
