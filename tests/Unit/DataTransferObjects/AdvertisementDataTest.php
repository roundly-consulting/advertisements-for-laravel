<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Models\Category;
use RoundlyConsulting\Advertisements\Tests\User;
use RoundlyConsulting\Advertisements\ValueObjects\Money;

it('exposes its properties with sensible defaults', function (): void {
    $data = new AdvertisementData(name: 'Ad', price: new Money(500, 'EUR'));

    expect($data->name)->toBe('Ad')
        ->and($data->price->getAmount())->toBe(500)
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
        price: new Money(500, 'USD'),
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
        ->and($data->price->getCurrency())->toBe('USD');
});

it('builds from a bare amount using the default currency', function (): void {
    config()->set('advertisements.default_currency', 'GBP');

    $data = AdvertisementData::fromAmount(name: 'Ad', amount: 1200);

    expect($data->price->getAmount())->toBe(1200)
        ->and($data->price->getCurrency())->toBe('GBP');
});

it('builds from a bare amount with an explicit currency', function (): void {
    $data = AdvertisementData::fromAmount(name: 'Ad', amount: 1200, currency: 'EUR');

    expect($data->price->getCurrency())->toBe('EUR');
});

it('accepts a category model, id or slug', function (): void {
    $category = Category::factory()->create();

    expect((new AdvertisementData(name: 'Ad', price: new Money(1, 'EUR'), category: $category))->category)
        ->toBe($category)
        ->and((new AdvertisementData(name: 'Ad', price: new Money(1, 'EUR'), category: 7))->category)
        ->toBe(7)
        ->and(AdvertisementData::fromAmount(name: 'Ad', amount: 1, category: 'bikes')->category)
        ->toBe('bikes');
});
