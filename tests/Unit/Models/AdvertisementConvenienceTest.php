<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Advertisements\Events\AdvertisementArchived;
use RoundlyConsulting\Advertisements\Events\AdvertisementDeleted;
use RoundlyConsulting\Advertisements\Events\AdvertisementExpired;
use RoundlyConsulting\Advertisements\Events\AdvertisementPublished;
use RoundlyConsulting\Advertisements\Events\AdvertisementUnpublished;
use RoundlyConsulting\Advertisements\Models\Advertisement;

it('reports the published boolean', function (): void {
    expect(Advertisement::factory()->published()->create()->isPublished())->toBeTrue()
        ->and(Advertisement::factory()->create()->isPublished())->toBeFalse();
});

it('reports the active boolean and excludes expired published ads', function (): void {
    expect(Advertisement::factory()->published()->create()->isActive())->toBeTrue();

    $expired = Advertisement::factory()->expired()->create();
    expect($expired->isActive())->toBeFalse()
        ->and($expired->isExpired())->toBeTrue();
});

it('reports scheduled and archived booleans', function (): void {
    expect(Advertisement::factory()->scheduled()->create()->isScheduled())->toBeTrue()
        ->and(Advertisement::factory()->archived()->create()->isArchived())->toBeTrue();
});

it('publishes fluently and fires the event', function (): void {
    Event::fake(AdvertisementPublished::class);
    $ad = Advertisement::factory()->create();

    $ad->publish();

    expect($ad->isPublished())->toBeTrue();
    Event::assertDispatched(AdvertisementPublished::class);
});

it('unpublishes fluently and fires the event', function (): void {
    Event::fake(AdvertisementUnpublished::class);
    $ad = Advertisement::factory()->published()->create();

    $ad->unpublish();

    expect($ad->published_at)->toBeNull();
    Event::assertDispatched(AdvertisementUnpublished::class);
});

it('expires fluently and fires the event', function (): void {
    Event::fake(AdvertisementExpired::class);
    $ad = Advertisement::factory()->published()->create();

    $ad->expire();

    expect($ad->isExpired())->toBeTrue();
    Event::assertDispatched(AdvertisementExpired::class);
});

it('archives fluently and fires the event', function (): void {
    Event::fake(AdvertisementArchived::class);
    $ad = Advertisement::factory()->create();

    $ad->archive();

    expect($ad->isArchived())->toBeTrue();
    Event::assertDispatched(AdvertisementArchived::class);
});

it('soft-deletes through the action and fires the event', function (): void {
    Event::fake(AdvertisementDeleted::class);
    $ad = Advertisement::factory()->create();

    $result = $ad->delete();

    expect($result)->toBeTrue()
        ->and($ad->fresh()->trashed())->toBeTrue();
    Event::assertDispatched(AdvertisementDeleted::class);
});
