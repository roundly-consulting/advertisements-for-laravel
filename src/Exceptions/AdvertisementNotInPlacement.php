<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Exceptions;

use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;

/**
 * Thrown by `Advertisements::for($ad)->track($placement)` when the ad does not run in
 * that placement — so a forged or stale click URL cannot credit a placement the ad was
 * never served in.
 */
final class AdvertisementNotInPlacement extends AdvertisementException
{
    public static function for(Advertisement $advertisement, Placement|int|string $placement): self
    {
        $reference = $placement instanceof Placement ? (string) $placement->slug : (string) $placement;

        return new self("Advertisement [{$advertisement->getKey()}] does not run in placement [{$reference}].");
    }
}
