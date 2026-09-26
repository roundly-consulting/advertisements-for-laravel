<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Advertisements\Actions\CreateAdvertisement;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Events\AdvertisementCreated;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Category;
use RoundlyConsulting\Advertisements\Tests\User;
use RoundlyConsulting\Money\Money;

it('creates an advertisement and dispatches an event', function (): void {
    Event::fake(AdvertisementCreated::class);

    $advertisement = app(CreateAdvertisement::class)->execute(
        new AdvertisementData(name: 'Testing adv', price: Money::ofMinor(100, 'EUR')),
    );

    Event::assertDispatched(
        fn (AdvertisementCreated $event): bool => $event->advertisement->is($advertisement),
    );

    expect($advertisement)
        ->toBeInstanceOf(Advertisement::class)
        ->name->toBe('Testing adv')
        ->and($advertisement->price->minor())->toBe('100')
        ->and($advertisement->price->currency()->code)->toBe('EUR');
});

it('creates an advertisement with author, meta, publish and expiry dates', function (): void {
    Carbon::setTestNow('2023-09-12 16:40:00');

    $user = User::create();
    $category = Category::factory()->create(['slug' => 'private']);

    $advertisement = app(CreateAdvertisement::class)->execute(new AdvertisementData(
        name: 'Testing',
        price: Money::ofMinor(50, 'EUR'),
        category: 'private',
        description: 'A description',
        author: $user,
        meta: new Collection(['featured' => true]),
        publishedAt: now()->addDay(),
        expiresAt: now()->addDays(2),
    ));

    expect($advertisement)
        ->name->toBe('Testing')
        ->category_id->toBe($category->id)
        ->description->toBe('A description')
        ->and($advertisement->price->minor())->toBe('50')
        ->and($advertisement->author->is($user))->toBeTrue()
        ->and($advertisement->meta->get('featured'))->toBeTrue()
        ->and($advertisement->published_at->format('d.m.Y H:i'))->toBe('13.09.2023 16:40')
        ->and($advertisement->expires_at->format('d.m.Y H:i'))->toBe('14.09.2023 16:40');

    Carbon::setTestNow();
});

it('uses the model class configured in the config', function (): void {
    config()->set('advertisements.model', Advertisement::class);

    $advertisement = app(CreateAdvertisement::class)->execute(
        new AdvertisementData(name: 'Configured', price: Money::ofMinor(100, 'EUR')),
    );

    expect($advertisement)->toBeInstanceOf(Advertisement::class);
});

it('creates a price-less advertisement', function (): void {
    $advertisement = app(CreateAdvertisement::class)->execute(
        new AdvertisementData(name: 'Free listing', price: null),
    );

    expect($advertisement->fresh()->price)->toBeNull()
        ->and($advertisement->fresh()->currency)->toBeNull();
});

it('stores a decimal price exactly', function (): void {
    $advertisement = app(CreateAdvertisement::class)->execute(
        AdvertisementData::fromDecimal('Cheap', '1.10', 'EUR'),
    );

    expect($advertisement->fresh()->price->minor())->toBe('110');
});
