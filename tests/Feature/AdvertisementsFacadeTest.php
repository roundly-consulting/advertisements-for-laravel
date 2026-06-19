<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\AliasLoader;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\ValueObjects\Money;

it('creates an advertisement through the facade', function (): void {
    $advertisement = Advertisements::create(
        new AdvertisementData(name: 'Faded ad', price: new Money(2500, 'EUR')),
    );

    expect($advertisement)
        ->toBeInstanceOf(Advertisement::class)
        ->name->toBe('Faded ad')
        ->and(Advertisement::query()->whereKey($advertisement->getKey())->exists())->toBeTrue();
});

it('updates an advertisement through the facade', function (): void {
    $advertisement = Advertisements::create(
        new AdvertisementData(name: 'Original', price: new Money(100, 'EUR')),
    );

    $advertisement = Advertisements::update(
        $advertisement,
        new AdvertisementData(name: 'Renamed', price: new Money(200, 'EUR')),
    );

    expect($advertisement->name)->toBe('Renamed')
        ->and($advertisement->price->getAmount())->toBe(200);
});

it('exposes a fresh query builder', function (): void {
    Advertisement::factory()->count(2)->create();

    expect(Advertisements::query())
        ->toBeInstanceOf(Builder::class)
        ->and(Advertisements::query()->count())->toBe(2);
});

it('registers the facade alias by default', function (): void {
    expect(AliasLoader::getInstance()->getAliases())
        ->toHaveKey('Advertisements', Advertisements::class);
});
