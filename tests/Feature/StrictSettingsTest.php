<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\Advertisements\Actions\RecordImpression;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Jobs\RecordAdvertisementEventJob;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Category;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Advertisements\Support\AdvertisementsConfig;
use RoundlyConsulting\Advertisements\ValueObjects\Targeting;
use RoundlyConsulting\Geolocation\Facades\Geolocation;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/*
 | Owner rule: a typo in a host's config fails loudly and never falls back silently. Any
 | `geo.match_when_unknown` other than `all` used to read as `untargeted_only`; junk media
 | widths were dropped; blank buckets, views and locales were cast to strings.
 */

beforeEach(function (): void {
    Storage::fake('public');
});

it('refuses a match_when_unknown typo instead of serving untargeted ads only (strict config)', function (string $value): void {
    config()->set('advertisements.geo.match_when_unknown', $value);
    Geolocation::fake();
    $placement = Placement::factory()->create();

    expect(fn () => Advertisements::targetedIn($placement, null)->pluck('id'))->toThrow(
        InvalidConfigurationException::class,
        "Configuration value [advertisements.geo.match_when_unknown] must be one of [untargeted_only, all], [{$value}] given.",
    );
})->with(['ALL', 'everything', 'untargeted']);

it('serves untargeted ads only when match_when_unknown is absent (strict config)', function (): void {
    config()->set('advertisements.geo.match_when_unknown', null);
    Geolocation::fake();
    $placement = Placement::factory()->create();
    $untargeted = Advertisement::factory()->published()->create();
    $untargeted->placements()->attach($placement);
    $targeted = Advertisement::factory()->published()->create(['targeting' => new Targeting(countries: ['SK'])]);
    $targeted->placements()->attach($placement);

    expect(Advertisements::targetedIn($placement, null)->pluck('id'))
        ->toContain($untargeted->id)->not->toContain($targeted->id);
});

it('refuses a blank or wrong-typed string setting (strict config)', function (string $key, mixed $value, Closure $read): void {
    config()->set($key, $value);

    expect($read)->toThrow(InvalidConfigurationException::class, "[{$key}]");
})->with([
    'default currency' => ['advertisements.default_currency', '', fn () => AdvertisementData::fromMinor('Boots', 4900)],
    'fallback locale' => ['advertisements.fallback_locale', ['en'], fn () => Category::factory()->create()],
    'fallback bucket' => ['advertisements.media.fallback_bucket', ' ', fn () => (new Advertisement)->fallbackCreativeBucket()],
    'display variant' => ['advertisements.media.display_variant', 1, fn () => (new Advertisement)->displayVariant()],
    'bucket prefix' => ['advertisements.media.creative_bucket_prefix', '', fn () => (new Advertisement)->creativeBucketName('sidebar')],
    'text ad view' => ['advertisements.media.text_ad_view', '', fn () => AdvertisementsConfig::textAdView()],
    'media disk' => ['advertisements.media.disk', '', fn () => Advertisement::factory()->create()->addCreative(UploadedFile::fake()->image('a.jpg', 30, 25), Placement::factory()->create(['width' => 30, 'height' => 25]))],
]);

it('refuses junk responsive widths instead of dropping them (strict config)', function (mixed $widths): void {
    config()->set('advertisements.media.responsive_widths', $widths);
    $placement = Placement::factory()->create(['width' => 30, 'height' => 25]);

    expect(fn () => Advertisement::factory()->create()->addCreative(UploadedFile::fake()->image('a.jpg', 30, 25), $placement))
        ->toThrow(InvalidConfigurationException::class, '[advertisements.media.responsive_widths');
})->with([
    'a string' => ['320,640'],
    'a junk entry' => [[320, 'wide']],
    'a zero entry' => [[0]],
]);

it('reads canonical widths and leaves the media default when unset (strict config)', function (): void {
    config()->set('advertisements.media.responsive_widths', [320, '640']);
    expect(AdvertisementsConfig::responsiveWidths())->toBe([320, 640]);

    config()->set('advertisements.media.responsive_widths', null);
    expect(AdvertisementsConfig::responsiveWidths())->toBeNull();
});

it('refuses a blank tracking queue or connection when buffered (strict config)', function (string $key, mixed $value): void {
    config()->set('advertisements.tracking.buffered', true);
    config()->set($key, $value);
    Queue::fake();

    expect(fn () => app(RecordImpression::class)->execute(Advertisement::factory()->create(), 'sidebar'))
        ->toThrow(InvalidConfigurationException::class, "[{$key}]");
})->with([
    'connection blank' => ['advertisements.tracking.connection', ''],
    'queue array' => ['advertisements.tracking.queue', ['ads']],
]);

it('buffers onto the configured queue topology (strict config)', function (): void {
    config()->set('advertisements.tracking.buffered', true);
    config()->set('advertisements.tracking.connection', 'redis');
    config()->set('advertisements.tracking.queue', 'ads');
    Queue::fake();

    app(RecordImpression::class)->execute(Advertisement::factory()->create(), 'sidebar');

    Queue::assertPushed(RecordAdvertisementEventJob::class, fn (RecordAdvertisementEventJob $job): bool => $job->connection === 'redis' && $job->queue === 'ads');
});

it('reports a broken setting as INVALID in about (strict config)', function (): void {
    config()->set('advertisements.geo.match_when_unknown', 'everything');
    config()->set('advertisements.default_currency', '');
    config()->set('advertisements.media.responsive_widths', 'wide');

    Artisan::call('about', ['--only' => 'advertisements', '--json' => true]);

    /** @var array{advertisements: array<string, string>} $about */
    $about = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($about['advertisements'])
        ->default_currency->toBe('INVALID')
        ->creatives->toBe('INVALID')
        ->geo_targeting->toContain('INVALID');
});
