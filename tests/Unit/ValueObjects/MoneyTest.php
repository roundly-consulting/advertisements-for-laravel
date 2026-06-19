<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\ValueObjects\Money;

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

it('falls back to a plain string when the locale is invalid', function (): void {
    // A malformed locale makes NumberFormatter throw on construction, exercising
    // the manual fallback branch.
    $money = new Money(1500, 'EUR');

    expect($money->format('not-a-locale'))->toBe('EUR 15.00');
});
