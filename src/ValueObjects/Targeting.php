<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use RoundlyConsulting\Geolocation\DataTransferObjects\Coordinates;
use RoundlyConsulting\Geolocation\DataTransferObjects\Location;

/**
 * An advertisement's geo-targeting rules: allowed/blocked countries and an optional
 * radius around a centre point. An empty value object (no rules) means "global" — the
 * ad targets every viewer.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class Targeting implements Arrayable, JsonSerializable
{
    /**
     * @param  list<string>  $countries  ISO country codes the ad is restricted to (empty = any)
     * @param  list<string>  $excludeCountries  ISO country codes the ad is blocked in
     */
    public function __construct(
        public array $countries = [],
        public array $excludeCountries = [],
        public ?Coordinates $center = null,
        public ?float $radiusKm = null,
    ) {}

    /**
     * Build from the stored JSON payload (or any loosely-typed array).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            countries: self::normalizeCountries($data['countries'] ?? []),
            excludeCountries: self::normalizeCountries($data['exclude_countries'] ?? []),
            center: self::coordinatesFrom($data['center'] ?? null),
            radiusKm: isset($data['radius_km']) && is_numeric($data['radius_km'])
                ? (float) $data['radius_km']
                : null,
        );
    }

    public function isEmpty(): bool
    {
        return $this->countries === []
            && $this->excludeCountries === []
            && $this->center === null;
    }

    /**
     * Whether a fully-resolved viewer location satisfies these rules (country allow/deny
     * plus the radius, when both a centre and radius are set).
     */
    public function matches(Location $location): bool
    {
        if (! $this->matchesCountry($location->countryIsoCode)) {
            return false;
        }

        if ($this->center !== null && $this->radiusKm !== null) {
            return $this->center->near($location->coordinates(), $this->radiusKm);
        }

        return true;
    }

    /**
     * Whether a bare coordinate satisfies the radius (country rules are skipped — unknown).
     */
    public function matchesCoordinates(Coordinates $coordinates): bool
    {
        if ($this->center === null || $this->radiusKm === null) {
            return true;
        }

        return $this->center->near($coordinates, $this->radiusKm);
    }

    public function matchesCountry(string $country): bool
    {
        $country = strtoupper($country);
        $allowed = array_map(strtoupper(...), $this->countries);
        $blocked = array_map(strtoupper(...), $this->excludeCountries);

        if ($blocked !== [] && in_array($country, $blocked, true)) {
            return false;
        }

        if ($allowed !== [] && ! in_array($country, $allowed, true)) {
            return false;
        }

        return true;
    }

    /**
     * @return array{countries: list<string>, exclude_countries: list<string>, center: array{latitude: float, longitude: float}|null, radius_km: float|null}
     */
    public function toArray(): array
    {
        return [
            'countries' => $this->countries,
            'exclude_countries' => $this->excludeCountries,
            'center' => $this->center?->toArray(),
            'radius_km' => $this->radiusKm,
        ];
    }

    /**
     * @return array{countries: list<string>, exclude_countries: list<string>, center: array{latitude: float, longitude: float}|null, radius_km: float|null}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return list<string>
     */
    private static function normalizeCountries(mixed $countries): array
    {
        if (! is_array($countries)) {
            return [];
        }

        $clean = [];

        foreach ($countries as $country) {
            if (is_string($country) && $country !== '') {
                $clean[] = strtoupper($country);
            }
        }

        return array_values(array_unique($clean));
    }

    private static function coordinatesFrom(mixed $center): ?Coordinates
    {
        if (! is_array($center)) {
            return null;
        }

        $latitude = $center['latitude'] ?? null;
        $longitude = $center['longitude'] ?? null;

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        return new Coordinates((float) $latitude, (float) $longitude);
    }
}
