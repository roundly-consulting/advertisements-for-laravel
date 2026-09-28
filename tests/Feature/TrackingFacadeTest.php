<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use RoundlyConsulting\Advertisements\AdvertisementTracker;
use RoundlyConsulting\Advertisements\DataTransferObjects\ImpressionData;
use RoundlyConsulting\Advertisements\Enums\AdvertisementEventType;
use RoundlyConsulting\Advertisements\Exceptions\AdvertisementException;
use RoundlyConsulting\Advertisements\Exceptions\AdvertisementNotInPlacement;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Jobs\RecordAdvertisementEventJob;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\Advertisements\Models\Placement;

it('records impressions and clicks in a placement through the facade', function (): void {
    $ad = Advertisement::factory()->create();
    $sidebar = Placement::factory()->create(['slug' => 'sidebar']);
    $ad->placements()->attach($sidebar);

    $tracker = Advertisements::for($ad)->track('sidebar');

    $impression = $tracker->impression(new ImpressionData(ip: '1.2.3.4'));
    $click = $tracker->click();

    expect($tracker)->toBeInstanceOf(AdvertisementTracker::class)
        ->and($impression)->toBeInstanceOf(AdvertisementEvent::class)
        ->and($impression->type)->toBe(AdvertisementEventType::Impression)
        ->and($impression->placement_id)->toBe($sidebar->id)
        ->and($click->type)->toBe(AdvertisementEventType::Click)
        ->and($ad->refresh()->impressions_count)->toBe(1)
        ->and($ad->clicks_count)->toBe(1)
        ->and(AdvertisementEvent::query()->count())->toBe(2);
});

it('records without a placement', function (): void {
    $ad = Advertisement::factory()->create();

    $event = Advertisements::for($ad)->track()->impression();

    expect($event?->placement_id)->toBeNull()
        ->and($ad->refresh()->impressions_count)->toBe(1);
});

it('returns null and queues the record when tracking is buffered', function (): void {
    config()->set('advertisements.tracking.buffered', true);
    Queue::fake();
    $ad = Advertisement::factory()->create();

    expect(Advertisements::for($ad)->track()->click())->toBeNull();

    Queue::assertPushed(RecordAdvertisementEventJob::class);
    expect(AdvertisementEvent::query()->count())->toBe(0);
});

it('refuses to track an ad in a placement it does not run in', function (): void {
    $ad = Advertisement::factory()->create();
    $other = Advertisement::factory()->create();
    $sidebar = Placement::factory()->create(['slug' => 'sidebar']);
    $other->placements()->attach($sidebar);

    expect(fn () => Advertisements::for($ad)->track('sidebar'))
        ->toThrow(AdvertisementNotInPlacement::class, 'does not run in placement [sidebar]')
        ->and(fn () => Advertisements::for($ad)->track($sidebar))->toThrow(AdvertisementNotInPlacement::class)
        ->and(fn () => Advertisements::for($ad)->track($sidebar->id))->toThrow(AdvertisementException::class)
        ->and(fn () => Advertisements::for($ad)->track('nowhere'))->toThrow(AdvertisementNotInPlacement::class);

    expect(AdvertisementEvent::query()->count())->toBe(0)
        ->and($ad->refresh()->impressions_count)->toBe(0);
});
