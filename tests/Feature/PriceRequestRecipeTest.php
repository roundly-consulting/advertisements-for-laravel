<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Money\Http\Resources\MoneyResource;
use RoundlyConsulting\Money\Rules\CurrencyCode;
use RoundlyConsulting\Money\Rules\MoneyAmount;

/**
 * The README's host recipe, end to end. It replaces controllers that computed
 * `(int) ceil($request->float('price') * 100)` — 111 cents for a 1.10 € ad.
 */
function jsonPriceRequest(mixed $price, string $currency = 'EUR'): Request
{
    return Request::create('/ads', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: (string) json_encode([
        'name' => 'Road bike',
        'price' => $price,
        'currency' => $currency,
    ]));
}

it('stores a JSON float price exactly through Request::money()', function (): void {
    $request = jsonPriceRequest(1.1);

    $request->validate([
        'price' => ['required', MoneyAmount::inCurrencyFrom('currency')->min('0')],
        'currency' => ['required', new CurrencyCode],
    ]);

    $ad = Advertisements::create(new AdvertisementData(
        name: (string) $request->string('name'),
        price: $request->money('price', currencyKey: 'currency'),
    ));

    expect($ad->fresh()->price->minor())->toBe('110')
        ->and(MoneyResource::from($ad->fresh()->price)?->toArray($request))
        ->toMatchArray(['minor' => '110', 'decimal' => '1.10', 'currency' => 'EUR']);
});

it('stores a form-encoded decimal price through fromDecimal()', function (): void {
    $request = Request::create('/ads', 'POST', ['name' => 'Road bike', 'price' => '19.99', 'currency' => 'USD']);

    $ad = Advertisements::create(AdvertisementData::fromDecimal(
        name: (string) $request->string('name'),
        amount: (string) $request->string('price'),
        currency: (string) $request->string('currency'),
    ));

    expect($ad->fresh()->price->minor())->toBe('1999')
        ->and($ad->fresh()->currency)->toBe('USD');
});

it('renders no price resource for a price-less ad', function (): void {
    $ad = Advertisements::create(new AdvertisementData(name: 'Free'));

    expect(MoneyResource::from($ad->fresh()->price))->toBeNull();
});
