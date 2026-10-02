<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Tests\Fixtures;

use RoundlyConsulting\Geolocation\DataTransferObjects\GeolocationQuery;
use RoundlyConsulting\Geolocation\DataTransferObjects\Location;
use RoundlyConsulting\Geolocation\Exceptions\DatabaseNotFoundException;
use RoundlyConsulting\Geolocation\GeolocationProvider;

/**
 * A geolocation provider that fails like an enabled MaxMind database whose file is missing —
 * one of the lookups geolocation lets throw rather than answer null.
 */
final class ThrowingGeolocationProvider implements GeolocationProvider
{
    public function locate(GeolocationQuery $query): ?Location
    {
        throw DatabaseNotFoundException::forPath('/missing/GeoLite2-City.mmdb');
    }
}
