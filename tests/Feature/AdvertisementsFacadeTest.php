<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\AliasLoader;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Money\Money;

it('creates an advertisement through the facade', function (): void {
    $advertisement = Advertisements::create(
        new AdvertisementData(name: 'Faded ad', price: Money::ofMinor(2500, 'EUR')),
    );

    expect($advertisement)
        ->toBeInstanceOf(Advertisement::class)
        ->name->toBe('Faded ad')
        ->and(Advertisement::query()->whereKey($advertisement->getKey())->exists())->toBeTrue();
});

it('updates an advertisement through the facade', function (): void {
    $advertisement = Advertisements::create(
        new AdvertisementData(name: 'Original', price: Money::ofMinor(100, 'EUR')),
    );

    $advertisement = Advertisements::update(
        $advertisement,
        new AdvertisementData(name: 'Renamed', price: Money::ofMinor(200, 'EUR')),
    );

    expect($advertisement->name)->toBe('Renamed')
        ->and($advertisement->price->minor())->toBe('200');
});

it('exposes a fresh query builder', function (): void {
    Advertisement::factory()->count(2)->create();

    expect(Advertisements::query())
        ->toBeInstanceOf(Builder::class)
        ->and(Advertisements::query()->count())->toBe(2);
});

it('exposes an active-only query', function (): void {
    Advertisement::factory()->published()->create();
    Advertisement::factory()->create();
    Advertisement::factory()->scheduled()->create();
    Advertisement::factory()->expired()->create();
    Advertisement::factory()->archived()->create();

    expect(Advertisements::active()->count())->toBe(1);
});

it('returns a random active ad or null', function (): void {
    expect(Advertisements::random())->toBeNull();

    $active = Advertisement::factory()->published()->create();

    expect(Advertisements::random()->id)->toBe($active->id);
});

it('returns a random active ad scoped to a placement', function (): void {
    $sidebar = Placement::factory()->create(['slug' => 'sidebar']);
    $active = Advertisement::factory()->published()->create();
    $active->placements()->attach($sidebar);

    $other = Advertisement::factory()->published()->create();

    expect(Advertisements::random('sidebar')->id)->toBe($active->id);
});

it('registers the facade alias by default', function (): void {
    expect(AliasLoader::getInstance()->getAliases())
        ->toHaveKey('Advertisements', Advertisements::class);
});
