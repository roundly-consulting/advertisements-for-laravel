<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Advertisement;
use RoundlyConsulting\Advertisements\ValueObjects\Money;

it('throws when the price is set to a non-money value', function (): void {
    $advertisement = new Advertisement;

    $advertisement->price = 1500;
})->throws(InvalidArgumentException::class);

it('clears the price columns when set to null', function (): void {
    $advertisement = Advertisement::factory()->create([
        'price' => new Money(1000, 'EUR'),
    ]);

    $advertisement->price = null;
    $advertisement->save();

    expect($advertisement->fresh()->price)->toBeNull()
        ->and($advertisement->fresh()->getAttributes()['currency'])->toBeNull();
});
