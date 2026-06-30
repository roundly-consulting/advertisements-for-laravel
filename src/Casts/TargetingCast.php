<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Advertisements\Exceptions\InvalidTargeting;
use RoundlyConsulting\Advertisements\ValueObjects\Targeting;

/**
 * Transparently stores a {@see Targeting} value object as JSON, denormalizing the optional
 * radius centre into the indexed `target_latitude` / `target_longitude` columns so the
 * bounding-box pre-filter can run in SQL.
 *
 * @implements CastsAttributes<Targeting, mixed>
 */
final class TargetingCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Targeting
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = is_array($value) ? $value : json_decode((string) $value, true);

        if (! is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return Targeting::fromArray($decoded);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [
                'targeting' => null,
                'target_latitude' => null,
                'target_longitude' => null,
            ];
        }

        if (! $value instanceof Targeting) {
            throw InvalidTargeting::notTargeting();
        }

        return [
            'targeting' => json_encode($value->toArray()),
            'target_latitude' => $value->center?->latitude,
            'target_longitude' => $value->center?->longitude,
        ];
    }
}
