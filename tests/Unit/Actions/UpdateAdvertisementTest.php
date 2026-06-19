<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Advertisements\Actions\CreateAdvertisement;
use RoundlyConsulting\Advertisements\Actions\UpdateAdvertisement;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Events\AdvertisementUpdated;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Tests\User;
use RoundlyConsulting\Advertisements\ValueObjects\Money;

it('updates an advertisement and dispatches an event', function (): void {
    $advertisement = app(CreateAdvertisement::class)->execute(
        new AdvertisementData(name: 'Testing adv', price: new Money(100, 'EUR')),
    );

    Event::fake(AdvertisementUpdated::class);

    $advertisement = app(UpdateAdvertisement::class)->execute(
        $advertisement,
        new AdvertisementData(name: 'Testing update adv', price: new Money(200, 'EUR')),
    );

    Event::assertDispatched(
        fn (AdvertisementUpdated $event): bool => $event->advertisement->is($advertisement),
    );

    expect($advertisement)
        ->toBeInstanceOf(Advertisement::class)
        ->name->toBe('Testing update adv')
        ->and($advertisement->price->getAmount())->toBe(200);
});

it('updates an advertisement with author and dates', function (): void {
    $advertisement = app(CreateAdvertisement::class)->execute(
        new AdvertisementData(name: 'Testing adv', price: new Money(100, 'EUR')),
    );

    Carbon::setTestNow('2023-09-12 16:40:00');

    $user = User::create();

    $advertisement = app(UpdateAdvertisement::class)->execute($advertisement, new AdvertisementData(
        name: 'Testing',
        price: new Money(50, 'EUR'),
        category: 'private',
        author: $user,
        publishedAt: now()->addDay(),
        expiresAt: now()->addDays(2),
    ));

    expect($advertisement)
        ->name->toBe('Testing')
        ->category->toBe('private')
        ->and($advertisement->price->getAmount())->toBe(50)
        ->and($advertisement->author->is($user))->toBeTrue()
        ->and($advertisement->published_at->format('d.m.Y H:i'))->toBe('13.09.2023 16:40')
        ->and($advertisement->expires_at->format('d.m.Y H:i'))->toBe('14.09.2023 16:40');

    Carbon::setTestNow();
});

it('dissociates the author when none is given on update', function (): void {
    $user = User::create();

    $advertisement = app(CreateAdvertisement::class)->execute(
        new AdvertisementData(name: 'With author', price: new Money(100, 'EUR'), author: $user),
    );

    expect($advertisement->author->is($user))->toBeTrue();

    $advertisement = app(UpdateAdvertisement::class)->execute(
        $advertisement,
        new AdvertisementData(name: 'Without author', price: new Money(100, 'EUR')),
    );

    expect($advertisement->fresh()->author)->toBeNull();
});
