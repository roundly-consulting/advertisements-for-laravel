<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Events\AdvertisementCreated;
use RoundlyConsulting\Advertisements\Events\AdvertisementUpdated;
use RoundlyConsulting\Advertisements\Models\Advertisement;

it('wraps the advertisement on the created event', function (): void {
    $advertisement = Advertisement::factory()->create();

    $event = new AdvertisementCreated($advertisement);

    expect($event->advertisement)->toBe($advertisement);
});

it('wraps the advertisement on the updated event', function (): void {
    $advertisement = Advertisement::factory()->create();

    $event = new AdvertisementUpdated($advertisement);

    expect($event->advertisement)->toBe($advertisement);
});
