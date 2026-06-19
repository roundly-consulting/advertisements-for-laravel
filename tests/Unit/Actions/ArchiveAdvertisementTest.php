<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Advertisements\Actions\ArchiveAdvertisement;
use RoundlyConsulting\Advertisements\Enums\AdvertisementStatus;
use RoundlyConsulting\Advertisements\Events\AdvertisementArchived;
use RoundlyConsulting\Advertisements\Models\Advertisement;

it('archives an advertisement and dispatches an event', function (): void {
    Event::fake(AdvertisementArchived::class);

    $advertisement = Advertisement::factory()->published()->create();

    $advertisement = app(ArchiveAdvertisement::class)->execute($advertisement);

    expect($advertisement->status)->toBe(AdvertisementStatus::Archived)
        ->and($advertisement->fresh()->getRawOriginal('status'))->toBe('archived');

    Event::assertDispatched(
        fn (AdvertisementArchived $event): bool => $event->advertisement->is($advertisement),
    );
});
