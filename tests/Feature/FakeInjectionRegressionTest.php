<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\AdvertisementManager;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Tests\Fixtures\AdSlot;

it('hands the fake to classes that constructor-inject the manager', function (): void {
    $fake = Advertisements::fake();

    $slot = app(AdSlot::class);

    expect($slot->ads)->toBe($fake)
        ->and($slot->ads)->toBeInstanceOf(AdvertisementManager::class);
});
