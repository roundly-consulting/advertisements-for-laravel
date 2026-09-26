<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Tests;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Advertisements\AdvertisementsServiceProvider;
use RoundlyConsulting\Geolocation\GeolocationServiceProvider;
use RoundlyConsulting\MediaLibrary\MediaLibraryServiceProvider;
use RoundlyConsulting\Sluggable\SluggableServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    /**
     * Every provider advertisements hard-requires, in registration order. A host
     * auto-discovers these; the suite must list them or the test environment is a fiction.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [
            MediaLibraryServiceProvider::class,
            GeolocationServiceProvider::class,
            SluggableServiceProvider::class,
            AdvertisementsServiceProvider::class,
        ];
    }

    /**
     * No package auto-loads its migrations (they are publish-only), so the suite runs them
     * itself — exactly like a host app does after publishing. Every source is named by
     * **provider class**, never by a hand-resolved path: the base case reflects each provider
     * to its own `database/migrations`, so this keeps working when a provider renames a file
     * or composer moves the package between a symlinked path repo and a real VCS install.
     *
     * media-library ships the `media` table the creative buckets persist into; sluggable ships
     * the `slug_history` table behind `advertisements.slugs.history`.
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [
            MediaLibraryServiceProvider::class,
            SluggableServiceProvider::class,
            AdvertisementsServiceProvider::class,
            __DIR__.'/database/migrations',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return [
            // The route-binding tests sign URLs, which needs a real app key.
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),

            // Media-library: store on a fakeable public disk, use the GD driver, and keep the
            // responsive ladder small so creative variant generation stays fast under test.
            'media.disk' => 'public',
            'media.image_driver' => 'gd',
            'media.responsive.widths' => [320, 640],

            // The locales the suite writes slugs in. Sluggable builds one unique index per
            // supported locale at migrate time and walks them for the `Any` binding chain, so
            // leaving this at the app default (`en` only) would leave `de`/`sk` unindexed and
            // unbindable — a narrower world than the multi-locale catalog this package serves.
            'sluggable.locales.supported' => ['en', 'de', 'sk'],
        ];
    }
}
