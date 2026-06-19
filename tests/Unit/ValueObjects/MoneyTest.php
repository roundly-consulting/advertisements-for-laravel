<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Exceptions\InvalidPrice;
use RoundlyConsulting\Advertisements\ValueObjects\Money;

it('adds and subtracts amounts in the same currency', function (): void {
    $a = new Money(1500, 'EUR');
    $b = new Money(500, 'EUR');

    expect($a->add($b)->getAmount())->toBe(2000)
        ->and($a->subtract($b)->getAmount())->toBe(1000)
        ->and($a->add($b)->getCurrency())->toBe('EUR');
});

it('rejects adding or subtracting different currencies', function (): void {
    $eur = new Money(100, 'EUR');
    $usd = new Money(100, 'USD');

    expect(fn () => $eur->add($usd))->toThrow(InvalidPrice::class);
    expect(fn () => $eur->subtract($usd))->toThrow(InvalidPrice::class);
});

it('reports whether the amount is zero', function (): void {
    expect((new Money(0, 'EUR'))->isZero())->toBeTrue()
        ->and((new Money(1, 'EUR'))->isZero())->toBeFalse();
});

it('exposes amount and currency', function (): void {
    $money = new Money(1500, 'eur');

    expect($money->getAmount())->toBe(1500)
        ->and($money->getCurrency())->toBe('EUR');
});

it('uppercases the currency code', function (): void {
    expect((new Money(100, 'usd'))->getCurrency())->toBe('USD');
});

it('compares equality by amount and currency', function (): void {
    $a = new Money(100, 'EUR');
    $b = new Money(100, 'EUR');
    $c = new Money(100, 'USD');
    $d = new Money(200, 'EUR');

    expect($a->equals($b))->toBeTrue()
        ->and($a->equals($c))->toBeFalse()
        ->and($a->equals($d))->toBeFalse();
});

it('formats the amount as a currency string', function (): void {
    $money = new Money(1500, 'EUR');

    expect($money->format('en_US'))->toContain('15')
        ->and((string) $money)->toBeString();
});

it('falls back to a plain string when the currency code is invalid', function (): void {
    // ICU requires a 3-letter ISO 4217 code; anything else makes
    // NumberFormatter::formatCurrency() return false, exercising the fallback.
    $money = new Money(1500, 'EU');

    expect($money->format('en_US'))->toBe('EU 15.00');
});
