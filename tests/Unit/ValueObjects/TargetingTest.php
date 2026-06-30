<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\ValueObjects\Targeting;
use RoundlyConsulting\Geolocation\DataTransferObjects\Coordinates;

it('treats an empty targeting as global', function (): void {
    $targeting = new Targeting;

    expect($targeting->isEmpty())->toBeTrue()
        ->and($targeting->matches(location('SK')))->toBeTrue()
        ->and($targeting->matches(location('DE')))->toBeTrue();
});

it('matches a country allow list case-insensitively', function (): void {
    $targeting = new Targeting(countries: ['sk']);

    expect($targeting->matchesCountry('SK'))->toBeTrue()
        ->and($targeting->matchesCountry('DE'))->toBeFalse();
});

it('blocks a denied country even when allowed', function (): void {
    $targeting = new Targeting(countries: ['SK', 'DE'], excludeCountries: ['DE']);

    expect($targeting->matchesCountry('SK'))->toBeTrue()
        ->and($targeting->matchesCountry('DE'))->toBeFalse();
});

it('matches a location within the radius only', function (): void {
    $targeting = new Targeting(center: new Coordinates(48.1486, 17.1077), radiusKm: 50.0);

    expect($targeting->matches(location('SK', 48.2190, 17.4000)))->toBeTrue()
        ->and($targeting->matches(location('CZ', 50.0755, 14.4378)))->toBeFalse();
});

it('matches bare coordinates by radius while ignoring country rules', function (): void {
    $targeting = new Targeting(countries: ['SK'], center: new Coordinates(48.1486, 17.1077), radiusKm: 50.0);

    expect($targeting->matchesCoordinates(new Coordinates(48.2190, 17.4000)))->toBeTrue()
        ->and($targeting->matchesCoordinates(new Coordinates(50.0755, 14.4378)))->toBeFalse();
});

it('round-trips through its array form', function (): void {
    $targeting = new Targeting(
        countries: ['SK'],
        excludeCountries: ['DE'],
        center: new Coordinates(48.1486, 17.1077),
        radiusKm: 25.0,
    );

    $restored = Targeting::fromArray($targeting->toArray());

    expect($restored->countries)->toBe(['SK'])
        ->and($restored->excludeCountries)->toBe(['DE'])
        ->and($restored->center?->latitude)->toBe(48.1486)
        ->and($restored->radiusKm)->toBe(25.0);
});

it('ignores malformed centre coordinates from stored data', function (): void {
    $targeting = Targeting::fromArray(['countries' => ['SK'], 'center' => ['latitude' => 'x']]);

    expect($targeting->center)->toBeNull()
        ->and($targeting->countries)->toBe(['SK']);
});
