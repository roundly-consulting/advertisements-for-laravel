<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Exceptions\InvalidTargeting;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Advertisements\ValueObjects\Targeting;
use RoundlyConsulting\Geolocation\DataTransferObjects\Coordinates;

it('stores and restores targeting as a value object', function (): void {
    $ad = Advertisement::factory()->create([
        'targeting' => new Targeting(countries: ['SK'], center: new Coordinates(48.1486, 17.1077), radiusKm: 50.0),
    ]);

    $restored = $ad->fresh();

    expect($restored?->targeting)->toBeInstanceOf(Targeting::class)
        ->and($restored?->targeting?->countries)->toBe(['SK'])
        ->and($restored?->targeting?->radiusKm)->toBe(50.0);
});

it('denormalizes the centre into the indexed columns', function (): void {
    $ad = Advertisement::factory()->create([
        'targeting' => new Targeting(center: new Coordinates(48.1486, 17.1077), radiusKm: 10.0),
    ]);

    expect($ad->target_latitude)->toBe(48.1486)
        ->and($ad->target_longitude)->toBe(17.1077);
});

it('clears targeting and the centre columns when set to null', function (): void {
    $ad = Advertisement::factory()->create([
        'targeting' => new Targeting(center: new Coordinates(48.1486, 17.1077), radiusKm: 10.0),
    ]);

    $ad->targeting = null;
    $ad->save();

    expect($ad->fresh()?->targeting)->toBeNull()
        ->and($ad->fresh()?->target_latitude)->toBeNull();
});

it('rejects a non-targeting value', function (): void {
    $ad = Advertisement::factory()->make();

    expect(fn () => $ad->targeting = 'invalid')->toThrow(InvalidTargeting::class);
});

it('stores an empty targeting as untargeted', function (): void {
    $placement = Placement::factory()->create();
    $ad = Advertisement::factory()->published()->create(['targeting' => new Targeting]);
    $ad->placements()->attach($placement);

    $centreOnly = Advertisement::factory()->published()->create(['targeting' => new Targeting(center: new Coordinates(48.1486, 17.1077))]);
    $centreOnly->placements()->attach($placement);

    expect($ad->fresh()?->targeting)->toBeNull()
        ->and($ad->fresh()?->getRawOriginal('targeting'))->toBeNull()
        ->and($centreOnly->fresh()?->targeting)->toBeNull()
        ->and($centreOnly->fresh()?->target_latitude)->toBeNull()
        ->and(Advertisement::query()->forPlacement($placement)->targetedAt(null)->pluck('id')->sort()->values()->all())
        ->toBe([$ad->id, $centreOnly->id]);
});
