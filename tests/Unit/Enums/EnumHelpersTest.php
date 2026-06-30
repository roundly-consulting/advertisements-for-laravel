<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Enums\AdvertisementEventType;
use RoundlyConsulting\Advertisements\Enums\AdvertisementStatus;
use RoundlyConsulting\Enums\DataTransferObjects\EnumOption;
use RoundlyConsulting\Enums\Helpers;

it('exposes the enums-for-laravel helpers trait on the status enum', function (): void {
    expect(class_uses(AdvertisementStatus::class))
        ->toContain(Helpers::class);
});

it('builds readable labels for every status case', function (): void {
    expect(AdvertisementStatus::labels()->all())
        ->toBe(['Draft', 'Scheduled', 'Published', 'Expired', 'Archived']);

    expect(AdvertisementStatus::Draft->label())->toBe('Draft');
    expect(AdvertisementStatus::Scheduled->readable())->toBe('Scheduled');
});

it('exposes select-ready options for the status enum', function (): void {
    $options = AdvertisementStatus::options();

    expect($options)->toHaveCount(5)
        ->and($options->first())->toBeInstanceOf(EnumOption::class);

    expect($options->first()->value)->toBe('draft')
        ->and($options->first()->label)->toBe('Draft')
        ->and($options->first()->name)->toBe('Draft');

    expect(AdvertisementStatus::toArray())->toBe([
        'draft' => 'Draft',
        'scheduled' => 'Scheduled',
        'published' => 'Published',
        'expired' => 'Expired',
        'archived' => 'Archived',
    ]);
});

it('builds a validation rule from the status backed values', function (): void {
    expect(AdvertisementStatus::validationRule())
        ->toBe('in:draft,scheduled,published,expired,archived');
});

it('exposes the helpers trait on the event type enum', function (): void {
    expect(class_uses(AdvertisementEventType::class))
        ->toContain(Helpers::class);

    expect(AdvertisementEventType::labels()->all())->toBe(['Impression', 'Click']);
    expect(AdvertisementEventType::validationRule())->toBe('in:impression,click');
    expect(AdvertisementEventType::Click->label())->toBe('Click');
});

it('keeps the event type counter-column behaviour alongside the trait', function (): void {
    expect(AdvertisementEventType::Impression->counterColumn())->toBe('impressions_count')
        ->and(AdvertisementEventType::Click->counterColumn())->toBe('clicks_count');
});
