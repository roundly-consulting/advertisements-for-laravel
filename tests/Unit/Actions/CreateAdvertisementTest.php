<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Advertisements\Actions\CreateAdvertisement;
use RoundlyConsulting\Advertisements\Advertisement;
use RoundlyConsulting\Advertisements\Events\AdvertisementCreated;
use RoundlyConsulting\Advertisements\Tests\User;

it('creates an advertisement and dispatches an event', function (): void {
    Event::fake(AdvertisementCreated::class);

    $advertisement = app(CreateAdvertisement::class)->execute(
        name: 'Testing adv',
        price: 100,
        currency: 'EUR',
    );

    Event::assertDispatched(
        fn (AdvertisementCreated $event): bool => $event->advertisement->is($advertisement),
    );

    expect($advertisement)
        ->toBeInstanceOf(Advertisement::class)
        ->name->toBe('Testing adv')
        ->and($advertisement->price->getAmount())->toBe(100)
        ->and($advertisement->price->getCurrency())->toBe('EUR');
});

it('creates an advertisement with author, meta, publish and expiry dates', function (): void {
    Carbon::setTestNow('2023-09-12 16:40:00');

    $user = User::create();

    $advertisement = app(CreateAdvertisement::class)->execute(
        name: 'Testing',
        price: 50,
        currency: 'EUR',
        category: 'private',
        description: 'A description',
        author: $user,
        meta: new Collection(['featured' => true]),
        publishedAt: now()->addDay(),
        expiresAt: now()->addDays(2),
    );

    expect($advertisement)
        ->name->toBe('Testing')
        ->category->toBe('private')
        ->description->toBe('A description')
        ->and($advertisement->price->getAmount())->toBe(50)
        ->and($advertisement->author->is($user))->toBeTrue()
        ->and($advertisement->meta->get('featured'))->toBeTrue()
        ->and($advertisement->published_at->format('d.m.Y H:i'))->toBe('13.09.2023 16:40')
        ->and($advertisement->expires_at->format('d.m.Y H:i'))->toBe('14.09.2023 16:40');

    Carbon::setTestNow();
});

it('uses the model class configured in the config', function (): void {
    config()->set('advertisements.model', Advertisement::class);

    $advertisement = app(CreateAdvertisement::class)->execute(
        name: 'Configured',
        price: 100,
        currency: 'EUR',
    );

    expect($advertisement)->toBeInstanceOf(Advertisement::class);
});
