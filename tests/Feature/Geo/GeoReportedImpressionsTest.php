<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\DataTransferObjects\ImpressionData;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\Geolocation\GeolocationManager;

it('stamps the viewer country and coordinates onto an impression', function (): void {
    GeolocationManager::fake([
        '1.2.3.4' => location('SK', 48.1486, 17.1077, 'Bratislava', 'Bratislavský'),
    ]);

    $ad = Advertisement::factory()->published()->create();

    Advertisements::recordImpression($ad, null, new ImpressionData(ip: '1.2.3.4'));

    $event = AdvertisementEvent::query()->firstOrFail();

    expect($event->country_code)->toBe('SK')
        ->and($event->meta?->get('city'))->toBe('Bratislava')
        ->and($event->meta?->get('latitude'))->toBe(48.1486);
});

it('does not stamp when geo stamping is disabled', function (): void {
    config()->set('advertisements.geo.stamp_events', false);
    GeolocationManager::fake(['1.2.3.4' => location('SK')]);

    $ad = Advertisement::factory()->published()->create();

    Advertisements::recordImpression($ad, null, new ImpressionData(ip: '1.2.3.4'));

    expect(AdvertisementEvent::query()->firstOrFail()->country_code)->toBeNull();
});

it('records the event without a country when the ip is unresolved', function (): void {
    GeolocationManager::fake();

    $ad = Advertisement::factory()->published()->create();

    Advertisements::recordImpression($ad, null, new ImpressionData(ip: '9.9.9.9'));

    $event = AdvertisementEvent::query()->firstOrFail();

    expect($event->country_code)->toBeNull()
        ->and($ad->refresh()->impressions_count)->toBe(1);
});

it('stamps geo on the buffered tracking path', function (): void {
    config()->set('advertisements.tracking.buffered', true);
    config()->set('queue.default', 'sync');
    GeolocationManager::fake(['5.6.7.8' => location('DE', 52.52, 13.405, 'Berlin')]);

    $ad = Advertisement::factory()->published()->create();

    Advertisements::recordImpression($ad, null, new ImpressionData(ip: '5.6.7.8'));

    expect(AdvertisementEvent::query()->firstOrFail()->country_code)->toBe('DE');
});

it('reports impressions and clicks grouped by country', function (): void {
    GeolocationManager::fake([
        '1.1.1.1' => location('SK'),
        '2.2.2.2' => location('SK'),
        '3.3.3.3' => location('DE'),
    ]);

    $ad = Advertisement::factory()->published()->create();

    Advertisements::recordImpression($ad, null, new ImpressionData(ip: '1.1.1.1'));
    Advertisements::recordImpression($ad, null, new ImpressionData(ip: '2.2.2.2'));
    Advertisements::recordImpression($ad, null, new ImpressionData(ip: '3.3.3.3'));
    Advertisements::recordClick($ad, null, new ImpressionData(ip: '3.3.3.3'));

    expect($ad->impressionsByCountry())->toBe(['DE' => 1, 'SK' => 2])
        ->and($ad->clicksByCountry())->toBe(['DE' => 1]);
});
