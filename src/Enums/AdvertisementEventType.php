<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Enums;

enum AdvertisementEventType: string
{
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
