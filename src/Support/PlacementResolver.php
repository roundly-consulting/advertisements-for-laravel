<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Support;

use RoundlyConsulting\Advertisements\Models\Placement;

/**
 * Normalises a placement reference (model, id, or slug) to its primary key, so
 * scopes, relations, and tracking can accept any of the three interchangeably.
 *
 * A string is a slug first; a digit-only string no placement uses as its slug is then
 * taken as an id (form and route input arrives as strings), like sluggable's key fallback.
 */
final class PlacementResolver
{
    /**
     * Resolve a single placement reference to its key, or null when it cannot be
     * resolved (unknown slug or numeric-string id / null input).
     */
    public function resolveKey(Placement|int|string|null $placement): ?int
    {
        if ($placement === null) {
            return null;
        }

        if ($placement instanceof Placement) {
            return $placement->getKey();
        }

        if (is_int($placement)) {
            return $placement;
        }

        $key = PlacementModel::query()->whereSlug($placement)->value('id')
            ?? (KeyString::isKey($placement) ? PlacementModel::query()->whereKey((int) $placement)->value('id') : null);

        return $key === null ? null : (int) $key;
    }

    /**
     * Resolve a placement reference to the model, or null when it cannot be resolved.
     */
    public function resolve(Placement|int|string|null $placement): ?Placement
    {
        if ($placement === null || $placement instanceof Placement) {
            return $placement;
        }

        $key = $this->resolveKey($placement);

        return $key === null ? null : PlacementModel::query()->find($key);
    }

    /**
     * Resolve an iterable of placement references to a list of keys.
     *
     * @param  iterable<int, Placement|int|string>  $placements
     * @return list<int>
     */
    public function resolveKeys(iterable $placements): array
    {
        $keys = [];

        foreach ($placements as $placement) {
            $key = $this->resolveKey($placement);

            if ($key !== null) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
