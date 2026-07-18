<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Tests\Fixtures\SwappedModelsTestCase;
use RoundlyConsulting\Advertisements\Tests\TestCase;
use RoundlyConsulting\Geolocation\DataTransferObjects\Location;
use RoundlyConsulting\Geolocation\Enum\GeolocationType;

uses(TestCase::class)->in(
    'ArchTest.php',
    'Feature',
    'Unit',
);

// The model-swap proofs need all four `advertisements.…` model keys pointed at host
// subclasses BEFORE the providers boot, so they run on their own base case in their own
// directory. Pest binds a test case per directory, not per file.
uses(SwappedModelsTestCase::class)->in('Configured');

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
