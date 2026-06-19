<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Advertisements\Actions\RecordImpression;
use RoundlyConsulting\Advertisements\DataTransferObjects\ImpressionData;
use RoundlyConsulting\Advertisements\Enums\AdvertisementEventType;
use RoundlyConsulting\Advertisements\Events\ImpressionRecorded;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\Advertisements\Models\Placement;

it('records an impression, bumps the counter and fires the event', function (): void {
    Event::fake([ImpressionRecorded::class]);
    $ad = Advertisement::factory()->create();

    $event = app(RecordImpression::class)->execute($ad);

    expect($event)->toBeInstanceOf(AdvertisementEvent::class)
        ->and($event->type)->toBe(AdvertisementEventType::Impression)
        ->and(AdvertisementEvent::query()->count())->toBe(1)
        ->and($ad->refresh()->impressions_count)->toBe(1);

    Event::assertDispatched(ImpressionRecorded::class);
});

it('resolves the placement by model, id, slug and null', function (mixed $placement): void {
    $ad = Advertisement::factory()->create();
    Placement::factory()->create(['slug' => 'sidebar', 'id' => 99]);

    $reference = match ($placement) {
        'model' => Placement::query()->find(99),
        'id' => 99,
        'slug' => 'sidebar',
        default => null,
    };

    $event = app(RecordImpression::class)->execute($ad, $reference);

    expect($event->placement_id)->toBe($placement === 'null' ? null : 99);
})->with(['model', 'id', 'slug', 'null']);

it('defaults occurred_at to now and honours an explicit instant', function (): void {
    $ad = Advertisement::factory()->create();
    Carbon::setTestNow('2026-01-01 12:00:00');

    $default = app(RecordImpression::class)->execute($ad);
    expect($default->occurred_at->toDateTimeString())->toBe('2026-01-01 12:00:00');

    $explicit = app(RecordImpression::class)->execute($ad, null, new ImpressionData(
        occurredAt: Carbon::parse('2025-06-01 08:30:00'),
    ));
    expect($explicit->occurred_at->toDateTimeString())->toBe('2025-06-01 08:30:00');

    Carbon::setTestNow();
});

it('merges request context and custom meta', function (): void {
    $ad = Advertisement::factory()->create();

    $event = app(RecordImpression::class)->execute($ad, null, new ImpressionData(
        ip: '127.0.0.1',
        userAgent: 'PestBot',
        referrer: 'https://example.test',
        meta: new Collection(['campaign' => 'summer']),
    ));

    expect($event->meta->all())->toBe([
        'ip' => '127.0.0.1',
        'user_agent' => 'PestBot',
        'referrer' => 'https://example.test',
        'campaign' => 'summer',
    ]);
});

it('increments rather than overwrites across multiple records', function (): void {
    $ad = Advertisement::factory()->create();

    app(RecordImpression::class)->execute($ad);
    app(RecordImpression::class)->execute($ad);
    app(RecordImpression::class)->execute($ad);

    expect($ad->refresh()->impressions_count)->toBe(3);
});
