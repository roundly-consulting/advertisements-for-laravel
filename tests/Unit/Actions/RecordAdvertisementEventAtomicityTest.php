<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Advertisements\Actions\RecordAdvertisementEvent;
use RoundlyConsulting\Advertisements\Enums\AdvertisementEventType;
use RoundlyConsulting\Advertisements\Events\ImpressionRecorded;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;

it('rolls the event row back when the counter increment fails, so a retry cannot double it', function (): void {
    $ad = Advertisement::factory()->published()->create();
    Event::fake([ImpressionRecorded::class]);

    // The increment is an `updating` save of the ad; failing it stands in for a lost
    // connection or a deadlock between the two writes.
    Advertisement::updating(function (): void {
        throw new RuntimeException('counter write failed');
    });

    expect(fn () => app(RecordAdvertisementEvent::class)->execute($ad, AdvertisementEventType::Impression))
        ->toThrow(RuntimeException::class, 'counter write failed');

    expect(AdvertisementEvent::query()->count())->toBe(0)
        ->and(Advertisement::query()->whereKey($ad->id)->value('impressions_count'))->toBe(0);

    Event::assertNotDispatched(ImpressionRecorded::class);
});

it('announces the record only after the row and the counter are written', function (): void {
    $ad = Advertisement::factory()->published()->create();
    $seen = null;

    Event::listen(ImpressionRecorded::class, function (ImpressionRecorded $recorded) use (&$seen, $ad): void {
        $seen = [
            AdvertisementEvent::query()->whereKey($recorded->event->id)->exists(),
            Advertisement::query()->whereKey($ad->id)->value('impressions_count'),
        ];
    });

    app(RecordAdvertisementEvent::class)->execute($ad, AdvertisementEventType::Impression);

    expect($seen)->toBe([true, 1]);
});
