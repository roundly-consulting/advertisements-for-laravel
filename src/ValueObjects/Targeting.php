<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use RoundlyConsulting\Geolocation\DataTransferObjects\Coordinates;
use RoundlyConsulting\Geolocation\DataTransferObjects\Location;

/**
 * An advertisement's geo-targeting rules: allowed/blocked countries and an optional
 * radius around a centre point. Every rule set must hold for a viewer to match. An empty
 * value object (no rules) means "global" — the cast stores it as no targeting at all.
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

    /**
     * Whether no rule is set: no country list and no radius (a centre without a radius, or a
     * radius without a centre, is not a rule). An empty targeting is stored as untargeted.
     */
    public function isEmpty(): bool
    {
        return $this->countries === []
            && $this->excludeCountries === []
            && ! $this->hasRadius();
    }

    /**
     * Whether the viewer satisfies every rule. The viewer is a resolved Location, bare
     * Coordinates, or an ISO country code.
     *
     * A rule needs a fact about the viewer: the country lists need a country, the radius needs
     * coordinates. When the viewer lacks it (a radius for a viewer known only by country, a
     * country list for bare coordinates or a Location without a country), the rule cannot be
     * checked and counts as met only when `$unknownMatches` — the package passes
     * `geo.match_when_unknown === 'all'`.
     */
    public function matches(Location|Coordinates|string $viewer, bool $unknownMatches = false): bool
    {
        return $this->countryRulesMet(self::countryOf($viewer), $unknownMatches)
            && $this->radiusRuleMet(self::coordinatesOf($viewer), $unknownMatches);
    }

    /**
     * Whether the viewer tells targeting anything: a country, or coordinates. A Location with
     * neither (no country, 0,0) or an empty country code is an unknown viewer.
     */
    public static function canPlace(Location|Coordinates|string|null $viewer): bool
    {
        return $viewer !== null
            && (self::countryOf($viewer) !== null || self::coordinatesOf($viewer) !== null);
    }

    private function hasRadius(): bool
    {
        return $this->center !== null && $this->radiusKm !== null;
    }

    private function countryRulesMet(?string $country, bool $unknownMatches): bool
    {
        if ($this->countries === [] && $this->excludeCountries === []) {
            return true;
        }

        if ($country === null) {
            return $unknownMatches;
        }

        if (in_array($country, array_map(strtoupper(...), $this->excludeCountries), true)) {
            return false;
        }

        return $this->countries === []
            || in_array($country, array_map(strtoupper(...), $this->countries), true);
    }

    private function radiusRuleMet(?Coordinates $coordinates, bool $unknownMatches): bool
    {
        if ($this->center === null || $this->radiusKm === null) {
            return true;
        }

        if ($coordinates === null) {
            return $unknownMatches;
        }

        return $this->center->near($coordinates, $this->radiusKm);
    }

    private static function countryOf(Location|Coordinates|string $viewer): ?string
    {
        $country = match (true) {
            $viewer instanceof Location => $viewer->countryIsoCode,
            $viewer instanceof Coordinates => '',
            default => $viewer,
        };

        $country = strtoupper(trim($country));

        return $country === '' ? null : $country;
    }

    /**
     * A Location at 0,0 has no coordinates — geolocation's own marker for "not placed" —
     * while bare Coordinates are always taken as given.
     */
    private static function coordinatesOf(Location|Coordinates|string $viewer): ?Coordinates
    {
        if ($viewer instanceof Coordinates) {
            return $viewer;
        }

        if (! $viewer instanceof Location || ($viewer->latitude === 0.0 && $viewer->longitude === 0.0)) {
            return null;
        }

        return $viewer->coordinates();
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
