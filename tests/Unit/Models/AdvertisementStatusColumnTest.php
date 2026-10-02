<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Enums\AdvertisementStatus;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Advertisement;

beforeEach(fn () => Carbon::setTestNow('2024-03-15 10:00:00'));
afterEach(fn () => Carbon::setTestNow());

function storedStatus(Advertisement $advertisement): string
{
    return (string) Advertisement::query()->whereKey($advertisement->getKey())->toBase()->value('status');
}

it('writes the status column on create from the dates it is given', function (): void {
    $live = Advertisements::create(new AdvertisementData(name: 'Live', publishedAt: now()->subHour()));
    $scheduled = Advertisements::create(new AdvertisementData(name: 'Later', publishedAt: now()->addDay()));
    $over = Advertisements::create(new AdvertisementData(name: 'Over', publishedAt: now()->subWeek(), expiresAt: now()->subDay()));
    $draft = Advertisements::create(new AdvertisementData(name: 'Draft'));

    expect(storedStatus($live))->toBe('published')
        ->and(storedStatus($scheduled))->toBe('scheduled')
        ->and(storedStatus($over))->toBe('expired')
        ->and(storedStatus($draft))->toBe('draft')
        ->and(Advertisement::query()->where('status', AdvertisementStatus::Published->value)->pluck('id')->all())
        ->toBe([$live->id]);
});

it('rewrites the status column when an update moves the dates', function (): void {
    $advertisement = Advertisement::factory()->published()->create();

    Advertisements::update($advertisement, new AdvertisementData(name: 'Pulled', publishedAt: null));

    expect(storedStatus($advertisement))->toBe('draft')
        ->and($advertisement->status)->toBe(AdvertisementStatus::Draft);

    Advertisements::update($advertisement, new AdvertisementData(name: 'Back', publishedAt: now()->subMinute()));

    expect(storedStatus($advertisement))->toBe('published');
});

it('keeps an archived ad archived through an update', function (): void {
    $advertisement = Advertisement::factory()->published()->archived()->create();

    Advertisements::update($advertisement, new AdvertisementData(name: 'Edited', publishedAt: now()->subMinute()));

    expect(storedStatus($advertisement))->toBe('archived')
        ->and($advertisement->isArchived())->toBeTrue();
});
