<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Sluggable\Exceptions\SlugAlreadyTakenException;

afterEach(function (): void {
    app()->setLocale('en');
});

it('creates a placement with a slug and a translatable name', function (): void {
    $placement = Placement::factory()->create([
        'slug' => 'sidebar',
        'name' => ['en' => 'Sidebar'],
    ]);

    expect($placement->slug)->toBe('sidebar')
        ->and($placement->name)->toBe('Sidebar')
        ->and($placement->getTranslations('name'))->toBe(['en' => 'Sidebar']);
});

it('round-trips a multi-locale name', function (): void {
    $placement = Placement::factory()->create(['name' => ['en' => 'Header']]);

    $placement->setTranslation('name', 'de', 'Kopfzeile')->save();

    app()->setLocale('de');

    expect($placement->fresh()->name)->toBe('Kopfzeile');
});

it('exposes advertisements with pivot meta', function (): void {
    $placement = Placement::factory()->create();
    $ad = Advertisement::factory()->create();

    $placement->advertisements()->attach($ad, ['meta' => json_encode(['weight' => 5])]);

    $loaded = $placement->advertisements()->first();

    // Decoded, not compared as text: `meta` is an uncast pivot column, and `jsonb` re-renders
    // the document it parsed rather than echoing the exact bytes it was handed.
    expect($loaded)->not->toBeNull()
        ->and($loaded->id)->toBe($ad->id)
        ->and(json_decode((string) $loaded->pivot?->meta, true))->toBe(['weight' => 5]);
});

it('uses slug as its route key', function (): void {
    expect((new Placement)->getRouteKeyName())->toBe('slug');
});

it('generates the slug from the fallback-locale name when none is given', function (): void {
    config()->set('advertisements.fallback_locale', 'en');
    app()->setLocale('de');

    $placement = Placement::query()->create([
        'name' => ['de' => 'Seitenleiste', 'en' => 'Side Banner'],
    ]);

    expect($placement->slug)->toBe('side-banner');
});

it('suffixes a generated slug that is already taken', function (): void {
    Placement::factory()->create(['slug' => 'header']);

    $placement = Placement::query()->create(['name' => ['en' => 'Header']]);

    expect($placement->slug)->toBe('header-2');
});

it('keeps a manual slug byte-for-byte', function (): void {
    $placement = Placement::factory()->create(['slug' => 'sidebar_300x250']);

    expect($placement->fresh()?->slug)->toBe('sidebar_300x250');
});

it('rejects a manual slug another placement already holds', function (): void {
    Placement::factory()->create(['slug' => 'sidebar']);

    expect(fn () => Placement::factory()->create(['slug' => 'sidebar']))
        ->toThrow(SlugAlreadyTakenException::class);

    expect(Placement::query()->where('slug', 'sidebar')->count())->toBe(1);
});

it('rejects a manual slug a soft-deleted placement still holds', function (): void {
    Placement::factory()->create(['slug' => 'footer'])->delete();

    expect(fn () => Placement::factory()->create(['slug' => 'footer']))
        ->toThrow(SlugAlreadyTakenException::class);
});

/**
 * The placement slug is the storage key of every creative uploaded for it
 * (`creative:{slug}`). A rename that moved the slug would orphan all of them.
 */
it('keeps its slug and creative bucket when the name is renamed', function (): void {
    $placement = Placement::factory()->create(['slug' => 'sidebar', 'name' => ['en' => 'Sidebar']]);
    $ad = Advertisement::factory()->create();
    $bucketBefore = $ad->creativeBucketName($placement);

    $placement->update(['name' => ['en' => 'Right Rail']]);

    expect($placement->fresh()?->slug)->toBe('sidebar')
        ->and($ad->creativeBucketName($placement->fresh()))->toBe($bucketBefore)
        ->and($bucketBefore)->toBe('creative:sidebar');
});

it('finds a placement by slug', function (): void {
    $placement = Placement::factory()->create(['slug' => 'leaderboard']);

    expect(Placement::query()->whereSlug('leaderboard')->value('id'))->toBe($placement->id);
});
