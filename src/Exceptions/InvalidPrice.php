<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Exceptions;

/**
 * Thrown when a price attribute is given a value that cannot be stored as Money.
 */
final class InvalidPrice extends AdvertisementException
{
    public static function mustBeMoneyInstance(): self
    {
        return new self('The price attribute must be a Money instance.');
    }
}
