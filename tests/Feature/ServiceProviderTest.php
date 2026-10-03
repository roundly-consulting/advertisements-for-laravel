<?php

declare(strict_types=1);

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Advertisements\AdvertisementManager;
use RoundlyConsulting\Advertisements\AdvertisementsServiceProvider;
use RoundlyConsulting\Advertisements\Contracts\CreativeRenderer;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Support\CreativeResolver;

/**
 * Re-register the provider against the current config and report the resulting
 * class aliases. The alias is registered in `register()`, which Testbench runs
 * *before* a test's environment config lands — so an opt-out has to be proved by
 * re-registering, not by booting the app with the config already set.
 *
 * @return array<string, string>
 */
function reregisterAdvertisements(): array
{
    AliasLoader::getInstance()->setAliases([]);

    (new AdvertisementsServiceProvider(app()))->register();

    return AliasLoader::getInstance()->getAliases();
}

afterEach(function (): void {
    AliasLoader::getInstance()->setAliases([]);
});

it('merges the package config', function (): void {
    expect(config('advertisements.model'))->toBe(Advertisement::class);
});

it('binds the manager and the creative renderer', function (): void {
    expect(app(AdvertisementManager::class))->toBeInstanceOf(AdvertisementManager::class)
        ->and(app(CreativeRenderer::class))->toBeInstanceOf(CreativeResolver::class);
});

it('loads the package views', function (): void {
    expect(view()->exists('advertisements::text-ad'))->toBeTrue();
});

it('registers the facade alias by default', function (): void {
    expect(reregisterAdvertisements())->toHaveKey('Advertisements', Advertisements::class);
});

it('skips the facade alias when opted out', function (): void {
    config()->set('advertisements.register_facade_alias', false);

    expect(reregisterAdvertisements())->toBe([]);
});

/**
 * Migrations are PUBLISH-ONLY (fleet policy): the package must never add its own
 * migration directory to the migrator, so a host's `php artisan migrate` runs
 * exactly the files it published — never a second, differently-named copy of the
 * same `Schema::create()`.
 */
it('never auto-loads its migrations', function (): void {
    expect(app('migrator')->paths())
        ->not->toContain(realpath(__DIR__.'/../../database/migrations'));
});

it('publishes every migration timestamped, in dependency order', function (): void {
    $published = ServiceProvider::pathsToPublish(
        AdvertisementsServiceProvider::class,
        'advertisements-migrations',
    );

    $names = array_map(
        static fn (string $target): string => basename($target),
        array_values($published),
    );

    expect($names)->toHaveCount(7);

    foreach ($names as $name) {
        expect($name)->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_000\d_[a-z_]+\.php$/');
    }

    // Publishing preserves the source order (one second per file), so a host
    // migrates `categories` before the advertisements that constrain onto them.
    $sorted = $names;
    sort($sorted);

    expect($sorted)->toBe($names)
        ->and($names[0])->toContain('0001_create_categories_table')
        ->and($names[6])->toContain('0007_add_country_code_to_advertisement_events_table');
});

it('publishes the views', function (): void {
    $published = ServiceProvider::pathsToPublish(
        AdvertisementsServiceProvider::class,
        'advertisements-views',
    );

    expect(array_values($published))->toBe([resource_path('views/vendor/advertisements')]);
});

it('creates the schema when the published migrations are run', function (): void {
    expect(Schema::hasTable('advertisements'))->toBeTrue()
        ->and(Schema::hasTable('advertisement_events'))->toBeTrue();
});

it('contributes a secret-safe section to the about command', function (): void {
    config()->set('advertisements.media.disk', 's3-internal-creatives');
    config()->set('advertisements.tracking.buffered', true);
    config()->set('advertisements.tracking.connection', 'tenant-redis');
    config()->set('advertisements.tracking.queue', 'ads-tracking-eu');

    Artisan::call('about', ['--only' => 'advertisements']);

    $output = Artisan::output();

    // Guard the guard: an empty capture would make every negative below vacuous.
    expect($output)->toContain('Advertisement model')
        ->toContain('BUFFERED');

    // The creative disk and the tracking queue/connection are the HOST's storage
    // and queue topology — presence only, never the names.
    expect($output)->not->toContain('s3-internal-creatives')
        ->not->toContain('tenant-redis')
        ->not->toContain('ads-tracking-eu');
});
