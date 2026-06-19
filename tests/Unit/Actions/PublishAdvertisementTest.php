<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Advertisements\Actions\PublishAdvertisement;
use RoundlyConsulting\Advertisements\Enums\AdvertisementStatus;
use RoundlyConsulting\Advertisements\Events\AdvertisementPublished;
use RoundlyConsulting\Advertisements\Models\Advertisement;

it('publishes an advertisement now and dispatches an event', function (): void {
    Carbon::setTestNow('2024-01-10 12:00:00');
    Event::fake(AdvertisementPublished::class);

    $advertisement = Advertisement::factory()->create();

    $advertisement = app(PublishAdvertisement::class)->execute($advertisement);

    expect($advertisement->published_at->toDateTimeString())->toBe('2024-01-10 12:00:00')
        ->and($advertisement->status)->toBe(AdvertisementStatus::Published)
        ->and($advertisement->fresh()->getRawOriginal('status'))->toBe('published');

    Event::assertDispatched(
        fn (AdvertisementPublished $event): bool => $event->advertisement->is($advertisement),
    );

    Carbon::setTestNow();
});

it('schedules an advertisement when given a future date', function (): void {
    Carbon::setTestNow('2024-01-10 12:00:00');

    $advertisement = Advertisement::factory()->create();

    $advertisement = app(PublishAdvertisement::class)->execute($advertisement, now()->addWeek());

    expect($advertisement->status)->toBe(AdvertisementStatus::Scheduled)
        ->and($advertisement->fresh()->getRawOriginal('status'))->toBe('scheduled');

    Carbon::setTestNow();
});
