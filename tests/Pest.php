<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Tests\TestCase;
use RoundlyConsulting\Geolocation\DataTransferObjects\Location;
use RoundlyConsulting\Geolocation\Enum\GeolocationType;

uses(TestCase::class)->in(__DIR__);

/**
 * Build a geolocation Location for tests (seeded into GeolocationManager::fake()).
 */
function location(
    string $country,
    float $latitude = 0.0,
    float $longitude = 0.0,
    string $city = '',
    string $region = '',
): Location {
    return new Location(
        humanReadable: trim("{$city} {$country}"),
        street: '',
        city: $city,
        countryIsoCode: $country,
        latitude: $latitude,
        longitude: $longitude,
        type: GeolocationType::Ip,
        region: $region,
    );
}
