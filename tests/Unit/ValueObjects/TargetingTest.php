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

    expect($targeting->matches('SK'))->toBeTrue()
        ->and($targeting->matches('sk'))->toBeTrue()
        ->and($targeting->matches('DE'))->toBeFalse();
});

it('blocks a denied country even when allowed', function (): void {
    $targeting = new Targeting(countries: ['SK', 'DE'], excludeCountries: ['DE']);

    expect($targeting->matches('SK'))->toBeTrue()
        ->and($targeting->matches('DE'))->toBeFalse();
});

it('matches a location within the radius only', function (): void {
    $targeting = new Targeting(center: new Coordinates(48.1486, 17.1077), radiusKm: 50.0);

    expect($targeting->matches(location('SK', 48.2190, 17.4000)))->toBeTrue()
        ->and($targeting->matches(location('CZ', 50.0755, 14.4378)))->toBeFalse();
});

it('fails a rule the viewer lacks the fact for unless unknown facts match', function (): void {
    $radius = new Targeting(center: new Coordinates(48.1486, 17.1077), radiusKm: 50.0);
    $country = new Targeting(countries: ['SK']);

    expect($radius->matches('SK'))->toBeFalse()
        ->and($radius->matches('SK', unknownMatches: true))->toBeTrue()
        ->and($radius->matches(location('SK')))->toBeFalse()
        ->and($country->matches(new Coordinates(48.2190, 17.4000)))->toBeFalse()
        ->and($country->matches(new Coordinates(48.2190, 17.4000), unknownMatches: true))->toBeTrue()
        ->and($country->matches(location('', 48.2190, 17.4000)))->toBeFalse();
});

it('matches bare coordinates against both rules', function (): void {
    $targeting = new Targeting(countries: ['SK'], center: new Coordinates(48.1486, 17.1077), radiusKm: 50.0);

    expect($targeting->matches(new Coordinates(48.2190, 17.4000), unknownMatches: true))->toBeTrue()
        ->and($targeting->matches(new Coordinates(50.0755, 14.4378), unknownMatches: true))->toBeFalse()
        ->and($targeting->matches(new Coordinates(48.2190, 17.4000)))->toBeFalse();
});

it('tells a viewer it can place from an unknown one', function (): void {
    expect(Targeting::canPlace('SK'))->toBeTrue()
        ->and(Targeting::canPlace(new Coordinates(0.0, 0.0)))->toBeTrue()
        ->and(Targeting::canPlace(location('', 48.2, 17.4)))->toBeTrue()
        ->and(Targeting::canPlace(location('SK')))->toBeTrue()
        ->and(Targeting::canPlace(location('')))->toBeFalse()
        ->and(Targeting::canPlace(' '))->toBeFalse()
        ->and(Targeting::canPlace(null))->toBeFalse();
});

it('is empty without a complete rule', function (): void {
    expect((new Targeting(center: new Coordinates(48.1, 17.1)))->isEmpty())->toBeTrue()
        ->and((new Targeting(radiusKm: 10.0))->isEmpty())->toBeTrue()
        ->and((new Targeting(center: new Coordinates(48.1, 17.1), radiusKm: 10.0))->isEmpty())->toBeFalse()
        ->and((new Targeting(excludeCountries: ['RU']))->isEmpty())->toBeFalse();
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
