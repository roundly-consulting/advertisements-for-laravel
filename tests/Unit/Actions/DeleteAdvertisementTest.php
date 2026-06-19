<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Advertisements\Actions\DeleteAdvertisement;
use RoundlyConsulting\Advertisements\Events\AdvertisementDeleted;
use RoundlyConsulting\Advertisements\Models\Advertisement;

it('soft deletes an advertisement and dispatches an event', function (): void {
    Event::fake(AdvertisementDeleted::class);

    $advertisement = Advertisement::factory()->create();

    $result = app(DeleteAdvertisement::class)->execute($advertisement);

    expect($result)->toBeTrue()
        ->and($advertisement->trashed())->toBeTrue()
        ->and(Advertisement::withTrashed()->whereKey($advertisement->getKey())->exists())->toBeTrue();

    Event::assertDispatched(
        fn (AdvertisementDeleted $event): bool => $event->advertisement->is($advertisement),
    );
});
