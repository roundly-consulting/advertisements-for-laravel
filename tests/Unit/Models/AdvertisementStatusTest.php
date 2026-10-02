<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Advertisements\Enums\AdvertisementStatus;
use RoundlyConsulting\Advertisements\Models\Advertisement;

beforeEach(fn () => Carbon::setTestNow('2024-03-15 10:00:00'));
afterEach(fn () => Carbon::setTestNow());

it('reports draft when never published', function (): void {
    $advertisement = Advertisement::factory()->create([
        'published_at' => null,
        'expires_at' => null,
        'status' => 'draft',
    ]);

    expect($advertisement->status)->toBe(AdvertisementStatus::Draft);
});

it('reports scheduled when published in the future', function (): void {
    $advertisement = Advertisement::factory()->create([
        'published_at' => now()->addDay(),
        'status' => 'scheduled',
    ]);

    expect($advertisement->status)->toBe(AdvertisementStatus::Scheduled);
});

it('reports published when live and not expired', function (): void {
    $advertisement = Advertisement::factory()->create([
        'published_at' => now()->subDay(),
        'expires_at' => now()->addDay(),
        'status' => 'published',
    ]);

    expect($advertisement->status)->toBe(AdvertisementStatus::Published);
});

it('reports expired when the expiry has passed', function (): void {
    $advertisement = Advertisement::factory()->create([
        'published_at' => now()->subDays(2),
        'expires_at' => now()->subDay(),
        'status' => 'published',
    ]);

    expect($advertisement->status)->toBe(AdvertisementStatus::Expired);
});

it('reports archived regardless of timestamps', function (): void {
    $advertisement = Advertisement::factory()->create([
        'published_at' => now()->subDay(),
        'expires_at' => now()->addDay(),
        'status' => 'archived',
    ]);

    expect($advertisement->status)->toBe(AdvertisementStatus::Archived);
});

it('resolves a status set as an enum instance', function (): void {
    $advertisement = new Advertisement;
    $advertisement->setRawAttributes(['status' => AdvertisementStatus::Archived]);

    expect($advertisement->status)->toBe(AdvertisementStatus::Archived);
});

it('treats a publish instant exactly at now as published', function (): void {
    $advertisement = Advertisement::factory()->create([
        'published_at' => now(),
        'status' => 'published',
    ]);

    expect($advertisement->status)->toBe(AdvertisementStatus::Published);
});

it('keeps status and the is helpers fresh on one instance across lifecycle changes', function (): void {
    $advertisement = Advertisement::factory()->create();

    expect($advertisement->status)->toBe(AdvertisementStatus::Draft);

    $advertisement->publish();

    expect($advertisement->status)->toBe(AdvertisementStatus::Published)
        ->and($advertisement->isPublished())->toBeTrue();

    $advertisement->archive();

    expect($advertisement->status)->toBe(AdvertisementStatus::Archived)
        ->and($advertisement->isArchived())->toBeTrue()
        ->and($advertisement->isPublished())->toBeFalse();
});

it('re-reads the time overlay on every access instead of caching it', function (): void {
    $advertisement = Advertisement::factory()->published()->create(['expires_at' => now()->addMinutes(5)]);

    expect($advertisement->status)->toBe(AdvertisementStatus::Published)
        ->and($advertisement->isActive())->toBeTrue();

    Carbon::setTestNow(now()->addMinutes(10));

    expect($advertisement->status)->toBe(AdvertisementStatus::Expired)
        ->and($advertisement->isExpired())->toBeTrue()
        ->and($advertisement->isActive())->toBeFalse();
});
