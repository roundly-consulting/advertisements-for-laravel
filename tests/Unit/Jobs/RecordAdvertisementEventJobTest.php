<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use RoundlyConsulting\Advertisements\Actions\RecordImpression;
use RoundlyConsulting\Advertisements\Enums\AdvertisementEventType;
use RoundlyConsulting\Advertisements\Jobs\RecordAdvertisementEventJob;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;

it('pushes the job and writes nothing inline when buffered', function (): void {
    config()->set('advertisements.tracking.buffered', true);
    Queue::fake();
    $ad = Advertisement::factory()->create();

    app(RecordImpression::class)->execute($ad, 'sidebar');

    Queue::assertPushed(RecordAdvertisementEventJob::class);
    expect(AdvertisementEvent::query()->count())->toBe(0)
        ->and($ad->refresh()->impressions_count)->toBe(0);
});

it('produces the same end state when the job runs', function (): void {
    $ad = Advertisement::factory()->create();

    $job = new RecordAdvertisementEventJob($ad->id, AdvertisementEventType::Impression);
    app()->call([$job, 'handle']);

    expect(AdvertisementEvent::query()->count())->toBe(1)
        ->and($ad->refresh()->impressions_count)->toBe(1);
});

it('does nothing when the advertisement no longer exists', function (): void {
    $job = new RecordAdvertisementEventJob(999, AdvertisementEventType::Click);
    app()->call([$job, 'handle']);

    expect(AdvertisementEvent::query()->count())->toBe(0);
});

it('dispatches on the configured connection and queue', function (): void {
    config()->set('advertisements.tracking.buffered', true);
    config()->set('advertisements.tracking.connection', 'redis');
    config()->set('advertisements.tracking.queue', 'tracking');
    Queue::fake();
    $ad = Advertisement::factory()->create();

    app(RecordImpression::class)->execute($ad);

    Queue::assertPushed(RecordAdvertisementEventJob::class, function (RecordAdvertisementEventJob $job): bool {
        return $job->connection === 'redis' && $job->queue === 'tracking';
    });
});
