<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Enums;

use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;

/**
 * The default of every optional {@see AdvertisementData} argument: "the caller did not pass
 * this field". It never reaches a property — an omitted field reads as null — but it is what
 * lets `update()` tell an omitted field (keep the stored value) from an explicit `null` (clear
 * it). You never need to pass it yourself.
 */
enum Omitted
{
    case Value;
}
