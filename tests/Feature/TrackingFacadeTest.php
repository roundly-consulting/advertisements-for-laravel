<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\Advertisements\Models\Placement;

it('records impressions and clicks through the facade', function (): void {
    $ad = Advertisement::factory()->create();
    Placement::factory()->create(['slug' => 'sidebar']);

    Advertisements::recordImpression($ad, 'sidebar');
    Advertisements::recordClick($ad, 'sidebar');

    expect($ad->refresh()->impressions_count)->toBe(1)
        ->and($ad->clicks_count)->toBe(1)
        ->and(AdvertisementEvent::query()->count())->toBe(2);
});
