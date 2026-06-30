<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Enums;

use RoundlyConsulting\Enums\Helpers;

/**
 * The kind of tracked advertisement interaction.
 *
 * Uses the shared {@see Helpers} trait from `enums-for-laravel`, exposing
 * `labels()`, `options()`, `validationRule()`, `label()`, and friends.
 */
enum AdvertisementEventType: string
{
    use Helpers;

    case Impression = 'impression';
    case Click = 'click';

    /**
     * The denormalized counter column this event type increments on the ad.
     */
    public function counterColumn(): string
    {
        return match ($this) {
            self::Impression => 'impressions_count',
            self::Click => 'clicks_count',
        };
    }
}
