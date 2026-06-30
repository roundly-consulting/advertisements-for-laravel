<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Exceptions;

/**
 * Thrown when the targeting attribute is given a value that is not a Targeting instance.
 */
final class InvalidTargeting extends AdvertisementException
{
    public static function notTargeting(): self
    {
        return new self('The targeting attribute must be a Targeting instance or null.');
    }
}
