<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Advertisements\Actions\UnpublishAdvertisement;
use RoundlyConsulting\Advertisements\Enums\AdvertisementStatus;
use RoundlyConsulting\Advertisements\Events\AdvertisementUnpublished;
use RoundlyConsulting\Advertisements\Models\Advertisement;

it('returns an advertisement to draft and dispatches an event', function (): void {
    Event::fake(AdvertisementUnpublished::class);

    $advertisement = Advertisement::factory()->published()->create();

    $advertisement = app(UnpublishAdvertisement::class)->execute($advertisement);

    expect($advertisement->published_at)->toBeNull()
        ->and($advertisement->status)->toBe(AdvertisementStatus::Draft)
        ->and($advertisement->fresh()->getRawOriginal('status'))->toBe('draft');

    Event::assertDispatched(
        fn (AdvertisementUnpublished $event): bool => $event->advertisement->is($advertisement),
    );
});
