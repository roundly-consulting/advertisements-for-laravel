<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\Advertisements\Models\Placement;

it('relates an event to its advertisement and placement', function (): void {
    $ad = Advertisement::factory()->create();
    $placement = Placement::factory()->create();
    $event = AdvertisementEvent::factory()->impression()->for($ad)->create([
        'placement_id' => $placement->id,
    ]);

    expect($event->advertisement->is($ad))->toBeTrue()
        ->and($event->placement->is($placement))->toBeTrue();
});

it('exposes the events relation', function (): void {
    $ad = Advertisement::factory()->create();
    AdvertisementEvent::factory()->impression()->for($ad)->create();
    AdvertisementEvent::factory()->click()->for($ad)->create();

    expect($ad->events()->count())->toBe(2);
});

it('returns zero ctr with no impressions', function (): void {
    $ad = Advertisement::factory()->create();

    expect($ad->ctr())->toBe(0.0)
        ->and($ad->impressions())->toBe(0)
        ->and($ad->clicks())->toBe(0);
});

it('computes a non-trivial ctr', function (): void {
    $ad = Advertisement::factory()->create([
        'impressions_count' => 10,
        'clicks_count' => 2,
    ]);

    expect($ad->ctr())->toBe(0.2)
        ->and($ad->impressions())->toBe(10)
        ->and($ad->clicks())->toBe(2);
});
