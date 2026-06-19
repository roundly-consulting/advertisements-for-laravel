<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Advertisements\Actions\RecordClick;
use RoundlyConsulting\Advertisements\Enums\AdvertisementEventType;
use RoundlyConsulting\Advertisements\Events\ClickRecorded;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;

it('records a click, bumps the counter and fires the event', function (): void {
    Event::fake([ClickRecorded::class]);
    $ad = Advertisement::factory()->create();

    $event = app(RecordClick::class)->execute($ad);

    expect($event)->toBeInstanceOf(AdvertisementEvent::class)
        ->and($event->type)->toBe(AdvertisementEventType::Click)
        ->and(AdvertisementEvent::query()->count())->toBe(1)
        ->and($ad->refresh()->clicks_count)->toBe(1);

    Event::assertDispatched(ClickRecorded::class);
});
