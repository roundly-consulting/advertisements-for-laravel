<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Advertisements\Advertisement;
use RoundlyConsulting\Advertisements\Database\Factories\AdvertisementFactory;
use RoundlyConsulting\Advertisements\ValueObjects\Money;

it('casts price to a money value object', function (): void {
    $advertisement = Advertisement::factory()->create([
        'price' => new Money(2500, 'EUR'),
    ]);

    expect($advertisement->fresh()->price)
        ->toBeInstanceOf(Money::class)
        ->and($advertisement->fresh()->price->getAmount())->toBe(2500)
        ->and($advertisement->fresh()->price->getCurrency())->toBe('EUR');
});

it('casts price using a different currency', function (): void {
    $advertisement = Advertisement::factory()->create([
        'price' => new Money(100, 'USD'),
    ]);

    expect($advertisement->fresh()->price->getCurrency())->toBe('USD');
});

it('returns null price when no amount is stored', function (): void {
    $advertisement = Advertisement::factory()->create();
    $advertisement->forceFill(['price' => null, 'currency' => null])->save();

    expect($advertisement->fresh()->price)->toBeNull();
});

it('generates a slug from the name', function (): void {
    $advertisement = Advertisement::factory()->create(['name' => 'My Great Ad']);

    expect($advertisement->slug)->toBe('my-great-ad');
});

it('generates a unique slug when names collide', function (): void {
    $first = Advertisement::factory()->create(['name' => 'Same Name']);
    $second = Advertisement::factory()->create(['name' => 'Same Name']);

    expect($first->slug)->toBe('same-name')
        ->and($second->slug)->toBe('same-name-1');
});

it('regenerates the slug when the name changes', function (): void {
    $advertisement = Advertisement::factory()->create(['name' => 'Old Name']);

    $advertisement->update(['name' => 'New Name']);

    expect($advertisement->slug)->toBe('new-name');
});

it('returns a prunable query for expired advertisements', function (): void {
    Carbon::setTestNow('2023-09-08 09:30:00');

    $sql = (new Advertisement)->prunable()->toRawSql();

    expect($sql)->toContain('"expires_at" <= \'2023-09-08 09:30:00\'');

    Carbon::setTestNow();
});

it('resolves its factory', function (): void {
    expect(Advertisement::factory())->toBeInstanceOf(
        AdvertisementFactory::class,
    );
});

it('creates published and expired advertisements via factory states', function (): void {
    Carbon::setTestNow('2023-10-17 08:00:00');

    $published = Advertisement::factory()->published()->create();
    $expired = Advertisement::factory()->expired()->create();

    expect($published->published_at->format('Y-m-d'))->toBe('2023-10-16')
        ->and($expired->expires_at->format('Y-m-d'))->toBe('2023-10-16');

    Carbon::setTestNow();
});
