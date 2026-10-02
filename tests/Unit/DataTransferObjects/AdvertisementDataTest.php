<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Models\Category;
use RoundlyConsulting\Advertisements\Tests\User;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Exceptions\InvalidAmount;
use RoundlyConsulting\Money\Exceptions\RoundingNecessary;
use RoundlyConsulting\Money\Exceptions\UnknownCurrency;
use RoundlyConsulting\Money\Money;

it('exposes its properties with sensible defaults', function (): void {
    $data = new AdvertisementData(name: 'Ad', price: Money::ofMinor(500, 'EUR'));

    expect($data->name)->toBe('Ad')
        ->and($data->price->minor())->toBe('500')
        ->and($data->category)->toBeNull()
        ->and($data->description)->toBeNull()
        ->and($data->author)->toBeNull()
        ->and($data->meta)->toBeNull()
        ->and($data->publishedAt)->toBeNull()
        ->and($data->expiresAt)->toBeNull();
});

it('keeps every field passed in', function (): void {
    $user = User::create();
    $meta = new Collection(['featured' => true]);

    $data = new AdvertisementData(
        name: 'Ad',
        price: Money::ofMinor(500, 'USD'),
        category: 'bikes',
        description: 'desc',
        author: $user,
        meta: $meta,
        publishedAt: now(),
        expiresAt: now()->addDay(),
    );

    expect($data->category)->toBe('bikes')
        ->and($data->description)->toBe('desc')
        ->and($data->author->is($user))->toBeTrue()
        ->and($data->meta)->toBe($meta)
        ->and($data->price->currency()->code)->toBe('USD');
});

it('allows a price-less advertisement', function (): void {
    expect((new AdvertisementData(name: 'Ad'))->price)->toBeNull()
        ->and((new AdvertisementData(name: 'Ad', price: null))->price)->toBeNull();
});

it('builds from minor units using the default currency', function (): void {
    config()->set('advertisements.default_currency', 'GBP');

    $data = AdvertisementData::fromMinor(name: 'Ad', minor: 1200);

    expect($data->price->minor())->toBe('1200')
        ->and($data->price->currency()->code)->toBe('GBP');
});

it('builds from minor units with an explicit currency', function (): void {
    $data = AdvertisementData::fromMinor(name: 'Ad', minor: 1200, currency: Currency::of('USD'));

    expect($data->price->currency()->code)->toBe('USD');
});

it('normalises an integer string of minor units', function (): void {
    expect(AdvertisementData::fromMinor(name: 'Ad', minor: '00199', currency: 'EUR')->price->minor())->toBe('199');
});

it('accepts minor units wider than int64 as a string', function (): void {
    expect(AdvertisementData::fromMinor(name: 'Ad', minor: '100000000000000000000', currency: 'EUR')->price->minor())
        ->toBe('100000000000000000000');
});

it('refuses a float of minor units', function (): void {
    AdvertisementData::fromMinor(name: 'Ad', minor: 1.5, currency: 'EUR');
})->throws(TypeError::class);

it('refuses a fractional string of minor units', function (): void {
    AdvertisementData::fromMinor(name: 'Ad', minor: '10.5', currency: 'EUR');
})->throws(InvalidAmount::class);

/**
 * The regression this factory exists for: hosts computed `(int) ceil(1.1 * 100)`, which is
 * 111 — so a 1.10 € ad was stored as 1.11 €. The decimal path is exact.
 */
it('builds 1.10 from a decimal as exactly 110 minor units', function (): void {
    expect(AdvertisementData::fromDecimal(name: 'Ad', amount: '1.10', currency: 'EUR')->price->minor())->toBe('110');
});

it('builds from a decimal using the default currency', function (): void {
    config()->set('advertisements.default_currency', 'CZK');

    $data = AdvertisementData::fromDecimal(name: 'Ad', amount: '19.99');

    expect($data->price->minor())->toBe('1999')
        ->and($data->price->currency()->code)->toBe('CZK');
});

it('builds from a whole major amount', function (): void {
    expect(AdvertisementData::fromDecimal(name: 'Ad', amount: 25, currency: 'EUR')->price->minor())->toBe('2500');
});

it('respects the currency exponent', function (): void {
    expect(AdvertisementData::fromDecimal(name: 'Ad', amount: '1500', currency: 'JPY')->price->minor())->toBe('1500');
});

it('refuses a decimal finer than the currency allows instead of rounding', function (): void {
    AdvertisementData::fromDecimal(name: 'Ad', amount: '19.999', currency: 'EUR');
})->throws(RoundingNecessary::class);

it('refuses an unknown currency', function (): void {
    AdvertisementData::fromDecimal(name: 'Ad', amount: '1', currency: 'XYZ');
})->throws(UnknownCurrency::class);

it('passes every other field through both factories', function (): void {
    $user = User::create();
    $meta = new Collection(['featured' => true]);

    foreach ([
        AdvertisementData::fromMinor('Ad', 100, 'EUR', 'bikes', 'desc', $user, $meta, now(), now()->addDay()),
        AdvertisementData::fromDecimal('Ad', '1', 'EUR', 'bikes', 'desc', $user, $meta, now(), now()->addDay()),
    ] as $data) {
        expect($data->category)->toBe('bikes')
            ->and($data->description)->toBe('desc')
            ->and($data->author->is($user))->toBeTrue()
            ->and($data->meta)->toBe($meta)
            ->and($data->publishedAt)->not->toBeNull()
            ->and($data->expiresAt)->not->toBeNull();
    }
});

it('accepts a category model, id or slug', function (): void {
    $category = Category::factory()->create();

    expect((new AdvertisementData(name: 'Ad', price: Money::ofMinor(1, 'EUR'), category: $category))->category)
        ->toBe($category)
        ->and((new AdvertisementData(name: 'Ad', price: Money::ofMinor(1, 'EUR'), category: 7))->category)
        ->toBe(7)
        ->and(AdvertisementData::fromMinor(name: 'Ad', minor: 1, category: 'bikes')->category)
        ->toBe('bikes');
});

it('tells an omitted field from an explicit null', function (): void {
    $data = new AdvertisementData(name: 'Ad', description: null);

    expect($data->provides('name'))->toBeTrue()
        ->and($data->provides('description'))->toBeTrue()
        ->and($data->description)->toBeNull()
        ->and($data->provides('price'))->toBeFalse()
        ->and($data->provides('category'))->toBeFalse()
        ->and($data->provides('author'))->toBeFalse()
        ->and($data->provides('meta'))->toBeFalse()
        ->and($data->provides('publishedAt'))->toBeFalse()
        ->and($data->provides('expiresAt'))->toBeFalse()
        ->and($data->provides('nonsense'))->toBeFalse();
});

it('provides the price from a factory and forwards only the passed optional fields', function (): void {
    $data = AdvertisementData::fromDecimal(name: 'Ad', amount: '1.10', expiresAt: null);

    expect($data->provides('price'))->toBeTrue()
        ->and($data->provides('expiresAt'))->toBeTrue()
        ->and($data->provides('category'))->toBeFalse()
        ->and(AdvertisementData::fromMinor(name: 'Ad', minor: 110)->provides('description'))->toBeFalse();
});
