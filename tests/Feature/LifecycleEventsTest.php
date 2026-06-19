<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Advertisements\Events\AdvertisementArchived;
use RoundlyConsulting\Advertisements\Events\AdvertisementDeleted;
use RoundlyConsulting\Advertisements\Events\AdvertisementExpired;
use RoundlyConsulting\Advertisements\Events\AdvertisementPublished;
use RoundlyConsulting\Advertisements\Events\AdvertisementUnpublished;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Advertisement;

beforeEach(function (): void {
    Event::fake([
        AdvertisementPublished::class,
        AdvertisementUnpublished::class,
        AdvertisementExpired::class,
        AdvertisementArchived::class,
        AdvertisementDeleted::class,
    ]);
});

it('dispatches a published event from the facade', function (): void {
    Advertisements::publish(Advertisement::factory()->create());

    Event::assertDispatched(AdvertisementPublished::class);
});

it('dispatches an unpublished event from the facade', function (): void {
    Advertisements::unpublish(Advertisement::factory()->published()->create());

    Event::assertDispatched(AdvertisementUnpublished::class);
});

it('dispatches an expired event from the facade', function (): void {
    Advertisements::expire(Advertisement::factory()->published()->create());

    Event::assertDispatched(AdvertisementExpired::class);
});

it('dispatches an archived event from the facade', function (): void {
    Advertisements::archive(Advertisement::factory()->published()->create());

    Event::assertDispatched(AdvertisementArchived::class);
});

it('dispatches a deleted event from the facade', function (): void {
    Advertisements::delete(Advertisement::factory()->create());

    Event::assertDispatched(AdvertisementDeleted::class);
});
