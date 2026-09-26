<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\Money\Money;

it('intercepts tracking and writes no rows', function (): void {
    $fake = Advertisements::fake();
    $ad = Advertisement::factory()->create();

    Advertisements::recordImpression($ad, 'sidebar');
    Advertisements::recordClick($ad, 'sidebar');

    expect(AdvertisementEvent::query()->count())->toBe(0)
        ->and($ad->refresh()->impressions_count)->toBe(0)
        ->and($ad->clicks_count)->toBe(0);

    $fake->assertImpressionRecorded();
    $fake->assertClickRecorded();
});

it('matches an impression with a closure filter', function (): void {
    $fake = Advertisements::fake();
    $ad = Advertisement::factory()->create();

    Advertisements::recordImpression($ad, 'sidebar');

    $fake->assertImpressionRecorded(
        fn ($event): bool => $event->ad->is($ad) && $event->placement === 'sidebar',
    );
});

it('matches a click with a closure filter', function (): void {
    $fake = Advertisements::fake();
    $ad = Advertisement::factory()->create();

    Advertisements::recordClick($ad, 'header');

    $fake->assertClickRecorded(fn ($event): bool => $event->placement === 'header');
});

it('asserts nothing recorded when no tracking happened', function (): void {
    $fake = Advertisements::fake();

    $fake->assertNothingRecorded();
});

it('fails assertNothingRecorded when tracking happened', function (): void {
    $fake = Advertisements::fake();
    $ad = Advertisement::factory()->create();

    Advertisements::recordImpression($ad);

    expect(fn () => $fake->assertNothingRecorded())
        ->toThrow(AssertionFailedError::class);
});

it('fails assertImpressionRecorded when none matched', function (): void {
    $fake = Advertisements::fake();

    expect(fn () => $fake->assertImpressionRecorded())
        ->toThrow(AssertionFailedError::class);
});

it('forwards non-tracking calls to the real manager', function (): void {
    Advertisements::fake();

    $ad = Advertisements::create(
        new AdvertisementData(name: 'Forwarded', price: Money::ofMinor(100, 'EUR')),
    );

    expect($ad)->toBeInstanceOf(Advertisement::class)
        ->and(Advertisement::query()->whereKey($ad->getKey())->exists())->toBeTrue();
});

it('returns the same fake on repeated fake() calls', function (): void {
    $first = Advertisements::fake();
    $second = Advertisements::fake();

    expect($first)->toBe($second);
});
