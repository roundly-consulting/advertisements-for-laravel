<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Exceptions;

use RoundlyConsulting\Advertisements\Models\Advertisement;

/**
 * Thrown by `Advertisements::for($ad)->track()` when the ad is not live — a draft, scheduled,
 * expired or archived ad — so a stale or forged impression/click URL cannot credit it.
 */
final class AdvertisementNotActive extends AdvertisementException
{
    public static function for(Advertisement $advertisement): self
    {
        return new self("Advertisement [{$advertisement->getKey()}] is not live ({$advertisement->status->value}).");
    }
}
