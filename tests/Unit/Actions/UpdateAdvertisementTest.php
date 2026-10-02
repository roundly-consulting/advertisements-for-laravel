<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Advertisements\Actions\CreateAdvertisement;
use RoundlyConsulting\Advertisements\Actions\UpdateAdvertisement;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Enums\AdvertisementStatus;
use RoundlyConsulting\Advertisements\Events\AdvertisementUpdated;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Category;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Advertisements\Tests\User;
use RoundlyConsulting\Money\Money;

it('updates an advertisement and dispatches an event', function (): void {
    $advertisement = app(CreateAdvertisement::class)->execute(
        new AdvertisementData(name: 'Testing adv', price: Money::ofMinor(100, 'EUR')),
    );

    Event::fake(AdvertisementUpdated::class);

    $advertisement = app(UpdateAdvertisement::class)->execute(
        $advertisement,
        new AdvertisementData(name: 'Testing update adv', price: Money::ofMinor(200, 'EUR')),
    );

    Event::assertDispatched(
        fn (AdvertisementUpdated $event): bool => $event->advertisement->is($advertisement),
    );

    expect($advertisement)
        ->toBeInstanceOf(Advertisement::class)
        ->name->toBe('Testing update adv')
        ->and($advertisement->price->minor())->toBe('200');
});

it('updates an advertisement with author and dates', function (): void {
    $advertisement = app(CreateAdvertisement::class)->execute(
        new AdvertisementData(name: 'Testing adv', price: Money::ofMinor(100, 'EUR')),
    );

    Carbon::setTestNow('2023-09-12 16:40:00');

    $user = User::create();
    $category = Category::factory()->create(['slug' => 'private']);

    $advertisement = app(UpdateAdvertisement::class)->execute($advertisement, new AdvertisementData(
        name: 'Testing',
        price: Money::ofMinor(50, 'EUR'),
        category: 'private',
        author: $user,
        publishedAt: now()->addDay(),
        expiresAt: now()->addDays(2),
    ));

    expect($advertisement)
        ->name->toBe('Testing')
        ->category_id->toBe($category->id)
        ->and($advertisement->price->minor())->toBe('50')
        ->and($advertisement->author->is($user))->toBeTrue()
        ->and($advertisement->published_at->format('d.m.Y H:i'))->toBe('13.09.2023 16:40')
        ->and($advertisement->expires_at->format('d.m.Y H:i'))->toBe('14.09.2023 16:40');

    Carbon::setTestNow();
});

it('keeps the author when none is given and dissociates it on an explicit null', function (): void {
    $user = User::create();

    $advertisement = app(CreateAdvertisement::class)->execute(
        new AdvertisementData(name: 'With author', price: Money::ofMinor(100, 'EUR'), author: $user),
    );

    expect($advertisement->author->is($user))->toBeTrue();

    $advertisement = app(UpdateAdvertisement::class)->execute(
        $advertisement,
        new AdvertisementData(name: 'Still authored', price: Money::ofMinor(100, 'EUR')),
    );

    expect($advertisement->fresh()->author?->is($user))->toBeTrue();

    $advertisement = app(UpdateAdvertisement::class)->execute(
        $advertisement,
        new AdvertisementData(name: 'Without author', author: null),
    );

    expect($advertisement->fresh()->author)->toBeNull();
});

it('re-denominates the price when the update carries another currency', function (): void {
    $advertisement = app(CreateAdvertisement::class)->execute(
        AdvertisementData::fromDecimal('Priced in euro', '10.00', 'EUR'),
    );

    $advertisement = app(UpdateAdvertisement::class)->execute(
        $advertisement,
        AdvertisementData::fromDecimal('Priced in dollars', '12.50', 'USD'),
    );

    expect($advertisement->fresh()->price->equals(Money::ofMinor(1250, 'USD')))->toBeTrue()
        ->and($advertisement->fresh()->currency)->toBe('USD');
});

it('clears the price and keeps the currency when the update has none', function (): void {
    $advertisement = app(CreateAdvertisement::class)->execute(
        AdvertisementData::fromMinor('Priced', 500, 'EUR'),
    );

    $advertisement = app(UpdateAdvertisement::class)->execute(
        $advertisement,
        new AdvertisementData(name: 'Now free', price: null),
    );

    expect($advertisement->fresh()->price)->toBeNull()
        ->and($advertisement->fresh()->currency)->toBe('EUR');
});

it('changes only the fields the update passes, so a price cut keeps a live ad live', function (): void {
    $user = User::create();
    $category = Category::factory()->create(['slug' => 'bikes']);
    $placement = Placement::factory()->create(['slug' => 'sidebar']);

    $advertisement = Advertisement::factory()->published()->create([
        'name' => 'Vintage road bike',
        'description' => 'Lightly used.',
        'category_id' => $category->id,
        'meta' => new Collection(['featured' => true]),
        'expires_at' => now()->addMonth(),
    ]);
    $advertisement->author()->associate($user)->save();
    $advertisement->placements()->attach($placement);

    $updated = Advertisements::update($advertisement, new AdvertisementData(
        name: 'Vintage road bike (reduced)',
        price: Money::ofMinor(19900, 'EUR'),
    ));

    $fresh = $updated->fresh();

    expect($fresh->name)->toBe('Vintage road bike (reduced)')
        ->and($fresh->price->minor())->toBe('19900')
        ->and($fresh->description)->toBe('Lightly used.')
        ->and($fresh->category_id)->toBe($category->id)
        ->and($fresh->meta?->all())->toBe(['featured' => true])
        ->and($fresh->author?->is($user))->toBeTrue()
        ->and($fresh->published_at)->not->toBeNull()
        ->and($fresh->expires_at)->not->toBeNull()
        ->and($fresh->status)->toBe(AdvertisementStatus::Published)
        ->and(Advertisements::in('sidebar')->pluck('id')->all())->toBe([$advertisement->id]);
});

it('clears a field the update passes as an explicit null', function (): void {
    $user = User::create();
    $category = Category::factory()->create();

    $advertisement = Advertisement::factory()->published()->create([
        'description' => 'Lightly used.',
        'category_id' => $category->id,
        'meta' => new Collection(['featured' => true]),
        'expires_at' => now()->addMonth(),
    ]);
    $advertisement->author()->associate($user)->save();

    $fresh = Advertisements::update($advertisement, new AdvertisementData(
        name: 'Stripped',
        category: null,
        description: null,
        author: null,
        meta: null,
        publishedAt: null,
        expiresAt: null,
    ))->fresh();

    expect($fresh->category_id)->toBeNull()
        ->and($fresh->description)->toBeNull()
        ->and($fresh->author)->toBeNull()
        ->and($fresh->meta)->toBeNull()
        ->and($fresh->published_at)->toBeNull()
        ->and($fresh->expires_at)->toBeNull()
        ->and($fresh->price)->not->toBeNull();
});

it('keeps the price when a factory-built update leaves the other fields out', function (): void {
    $category = Category::factory()->create();
    $advertisement = Advertisement::factory()->published()->create(['category_id' => $category->id]);

    $fresh = Advertisements::update($advertisement, AdvertisementData::fromDecimal('Repriced', '12.50'))->fresh();

    expect($fresh->price->toDecimal())->toBe('12.50')
        ->and($fresh->category_id)->toBe($category->id)
        ->and($fresh->status)->toBe(AdvertisementStatus::Published);
});
