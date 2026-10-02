<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Support\ViewerLocationResolver;
use RoundlyConsulting\Advertisements\Tests\Fixtures\ThrowingGeolocationProvider;
use RoundlyConsulting\Geolocation\Facades\Geolocation;

it('returns an already-resolved location as-is', function (): void {
    $location = location('SK');

    expect(app(ViewerLocationResolver::class)->resolve($location))->toBe($location);
});

it('resolves a raw ip via geolocation', function (): void {
    Geolocation::fake(['1.2.3.4' => location('SK')]);

    expect(app(ViewerLocationResolver::class)->resolve('1.2.3.4')?->countryIsoCode)->toBe('SK');
});

it('returns null when an ip cannot be resolved', function (): void {
    Geolocation::fake();

    expect(app(ViewerLocationResolver::class)->resolve('9.9.9.9'))->toBeNull();
});

it('memoizes a lookup per ip', function (): void {
    $fake = Geolocation::fake(['1.2.3.4' => location('SK')]);

    $resolver = app(ViewerLocationResolver::class);
    expect($resolver->resolve('1.2.3.4')?->countryIsoCode)->toBe('SK');

    // A memoized resolver ignores a later re-seed of the same key.
    $fake->seed('1.2.3.4', location('DE'));
    expect($resolver->resolve('1.2.3.4')?->countryIsoCode)->toBe('SK');
});

it('memoizes an unresolved lookup too', function (): void {
    $fake = Geolocation::fake();

    $resolver = app(ViewerLocationResolver::class);
    expect($resolver->resolve('9.9.9.9'))->toBeNull();

    $fake->seed('9.9.9.9', location('DE'));
    expect($resolver->resolve('9.9.9.9'))->toBeNull();
});

it('resolves to null when the lookup throws', function (): void {
    config()->set('geolocation.pipeline', ['broken']);
    config()->set('geolocation.providers.broken', ThrowingGeolocationProvider::class);

    expect(app(ViewerLocationResolver::class)->resolve('8.8.8.8'))->toBeNull();
});
