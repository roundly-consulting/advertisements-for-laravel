<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Exceptions\AdvertisementException;
use RoundlyConsulting\Advertisements\Exceptions\InvalidPrice;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\ValueObjects\Money;

it('throws an invalid price exception when set to a non-money value', function (): void {
    $advertisement = new Advertisement;

    $advertisement->price = 1500;
})->throws(InvalidPrice::class, 'The price attribute must be a Money instance.');

it('extends the base package exception', function (): void {
    expect(InvalidPrice::mustBeMoneyInstance())->toBeInstanceOf(AdvertisementException::class);
});

it('clears the price columns when set to null', function (): void {
    $advertisement = Advertisement::factory()->create([
        'price' => new Money(1000, 'EUR'),
    ]);

    $advertisement->price = null;
    $advertisement->save();

    expect($advertisement->fresh()->price)->toBeNull()
        ->and($advertisement->fresh()->getAttributes()['currency'])->toBeNull();
});
