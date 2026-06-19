<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Advertisements\Actions\ExpireAdvertisement;
use RoundlyConsulting\Advertisements\Enums\AdvertisementStatus;
use RoundlyConsulting\Advertisements\Events\AdvertisementExpired;
use RoundlyConsulting\Advertisements\Models\Advertisement;

it('expires an advertisement now and dispatches an event', function (): void {
    Carbon::setTestNow('2024-01-10 12:00:00');
    Event::fake(AdvertisementExpired::class);

    $advertisement = Advertisement::factory()->published()->create();

    $advertisement = app(ExpireAdvertisement::class)->execute($advertisement);

    expect($advertisement->expires_at->toDateTimeString())->toBe('2024-01-10 12:00:00')
        ->and($advertisement->status)->toBe(AdvertisementStatus::Expired)
        ->and($advertisement->fresh()->getRawOriginal('status'))->toBe('expired');

    Event::assertDispatched(
        fn (AdvertisementExpired $event): bool => $event->advertisement->is($advertisement),
    );

    Carbon::setTestNow();
});

it('sets a future expiry without flipping status yet', function (): void {
    Carbon::setTestNow('2024-01-10 12:00:00');

    $advertisement = Advertisement::factory()->published()->create();

    $advertisement = app(ExpireAdvertisement::class)->execute($advertisement, now()->addWeek());

    expect($advertisement->fresh()->getRawOriginal('status'))->toBe('published')
        ->and($advertisement->status)->toBe(AdvertisementStatus::Published);

    Carbon::setTestNow();
});
