<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\Advertisements\Models\Category;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Advertisements\Tests\Models\CustomAdvertisement;
use RoundlyConsulting\Advertisements\Tests\Models\CustomAdvertisementEvent;
use RoundlyConsulting\Advertisements\Tests\Models\CustomCategory;
use RoundlyConsulting\Advertisements\Tests\Models\CustomPlacement;

/**
 * The shipped factories build the model the host configured. A factory without a `modelName()`
 * override builds its packaged `$model`, so a host seeding through `Advertisement::factory()`
 * got packaged rows — without its own casts, events or observers. Exact classes, not
 * `instanceof`: the custom models extend the packaged ones, so `toBeInstanceOf` passes either way.
 */
it('builds the configured advertisement model', function (): void {
    expect(Advertisement::factory()->create()::class)->toBe(CustomAdvertisement::class)
        ->and(CustomAdvertisement::factory()->create()::class)->toBe(CustomAdvertisement::class);
});

it('builds the configured placement, category and event models', function (): void {
    expect(Placement::factory()->create()::class)->toBe(CustomPlacement::class)
        ->and(Category::factory()->create()::class)->toBe(CustomCategory::class)
        ->and(AdvertisementEvent::factory()->create()::class)->toBe(CustomAdvertisementEvent::class);
});

it('fires the host model events for the advertisement an event factory creates', function (): void {
    // Reading `$event->advertisement` back resolves through the seam either way, so the oracle
    // is the host class's own `created` event: it fires only for a row created as that class.
    CustomAdvertisement::resetCreationCount();

    AdvertisementEvent::factory()->create();

    expect(CustomAdvertisement::creationCount())->toBe(1);
});
