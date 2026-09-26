<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Advertisements\Actions\CreateAdvertisement;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Money\Exceptions\CurrencyMismatch;
use RoundlyConsulting\Money\Exceptions\InvalidMoneyValue;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * The `price` attribute runs on money-for-laravel's `AsMoney::currencyColumn('currency')`:
 * a decimal(38,0) minor-unit column plus the ad's own currency column.
 */
it('round-trips a price as money in minor units', function (): void {
    $advertisement = Advertisement::factory()->create(['price' => Money::ofMinor(2500, 'EUR')]);

    $price = $advertisement->fresh()->price;

    expect($price)->toBeInstanceOf(Money::class)
        ->and($price->minor())->toBe('2500')
        ->and($price->currency()->code)->toBe('EUR')
        ->and($advertisement->fresh()->currency)->toBe('EUR');
});

it('stores the currency the price is in', function (): void {
    $advertisement = Advertisement::factory()->create(['price' => Money::ofMinor(100, 'USD')]);

    expect($advertisement->fresh()->price->currency()->code)->toBe('USD')
        ->and($advertisement->fresh()->currency)->toBe('USD');
});

it('keeps a zero-exponent currency exact', function (): void {
    $advertisement = Advertisement::factory()->create(['price' => Money::ofMajor('1500', 'JPY')]);

    expect($advertisement->fresh()->price->minor())->toBe('1500')
        ->and($advertisement->fresh()->price->format('en'))->toContain('1,500');
});

it('returns a null price when no amount is stored', function (): void {
    $advertisement = Advertisement::factory()->create(['price' => null]);

    expect($advertisement->fresh()->price)->toBeNull();
});

/**
 * Behaviour change pinned: the old private cast nulled BOTH columns on a null write. money's
 * cast nulls only the amount and leaves the currency column alone — it may be shared.
 */
it('nulls only the amount and keeps the currency on a null write', function (): void {
    $advertisement = Advertisement::factory()->create(['price' => Money::ofMinor(1000, 'EUR')]);

    $advertisement->price = null;
    $advertisement->save();

    expect($advertisement->fresh()->price)->toBeNull()
        ->and($advertisement->fresh()->currency)->toBe('EUR');
});

it('refuses a raw number for the price', function (): void {
    $advertisement = new Advertisement;

    $advertisement->price = 1500;
})->throws(InvalidMoneyValue::class);

it('refuses to silently re-denominate the stored currency', function (): void {
    $advertisement = Advertisement::factory()->create(['price' => Money::ofMinor(1000, 'EUR')]);

    $advertisement->price = Money::ofMinor(1000, 'USD');
})->throws(CurrencyMismatch::class);

it('re-denominates when the currency column is set first', function (): void {
    $advertisement = Advertisement::factory()->create(['price' => Money::ofMinor(1000, 'EUR')]);

    $advertisement->fill(['currency' => 'USD', 'price' => Money::ofMinor(1200, 'USD')])->save();

    expect($advertisement->fresh()->price->equals(Money::ofMinor(1200, 'USD')))->toBeTrue();
});

it('does not dirty the model when an equal price is re-assigned', function (): void {
    $advertisement = Advertisement::factory()->create(['price' => Money::ofMinor(1999, 'EUR')])->fresh();

    $advertisement->price = Money::ofMajor('19.99', 'EUR');

    expect($advertisement->isDirty('price'))->toBeFalse();
});

it('orders by price numerically', function (): void {
    Advertisement::factory()->create(['name' => 'ten', 'price' => Money::ofMinor(1000, 'EUR')]);
    Advertisement::factory()->create(['name' => 'nine', 'price' => Money::ofMinor(900, 'EUR')]);
    Advertisement::factory()->create(['name' => 'hundred', 'price' => Money::ofMinor(10000, 'EUR')]);

    expect(Advertisement::query()->orderBy('price')->get()->map->name->all())
        ->toBe(['nine', 'ten', 'hundred']);
});

it('serializes the price as the money array', function (): void {
    $advertisement = Advertisement::factory()->create(['price' => Money::ofMinor(1999, 'EUR')]);

    expect($advertisement->fresh()->toArray()['price'])
        ->toBe(['minor' => '1999', 'decimal' => '19.99', 'currency' => 'EUR']);
});

/**
 * Beyond int64: decimal(38,0) holds every digit on postgres, while SQLite would store a REAL,
 * so the cast refuses the write there instead of losing precision.
 */
it('round-trips a price wider than int64 on postgres', function (): void {
    $advertisement = app(CreateAdvertisement::class)->execute(
        AdvertisementData::fromMinor('Whale', '100000000000000000000', 'EUR'),
    );

    expect($advertisement->fresh()->price->minor())->toBe('100000000000000000000')
        ->and((string) DB::table('advertisements')->value('price'))->toBe('100000000000000000000');
})->skip(fn (): bool => DriverMatrix::driver() !== 'pgsql', 'needs a numeric column wider than int64');

it('refuses a price wider than int64 on sqlite', function (): void {
    app(CreateAdvertisement::class)->execute(
        AdvertisementData::fromMinor('Whale', '100000000000000000000', 'EUR'),
    );
})->throws(InvalidMoneyValue::class)
    ->skip(fn (): bool => DriverMatrix::driver() !== 'sqlite', 'the int64 guard is sqlite-only');

it('fails loud on a corrupt stored amount', function (): void {
    $advertisement = Advertisement::factory()->create(['price' => Money::ofMinor(1000, 'EUR')]);

    DB::table('advertisements')->where('id', $advertisement->id)->update(['currency' => null]);

    expect(fn () => $advertisement->fresh()->price)->toThrow(InvalidMoneyValue::class);
});
