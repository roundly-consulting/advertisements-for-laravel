<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Support;

use RoundlyConsulting\Advertisements\Models\Placement;

/**
 * Normalises a placement reference (model, id, or slug) to its primary key, so
 * scopes, relations, and tracking can accept any of the three interchangeably.
 */
final class PlacementResolver
{
    /**
     * Resolve a single placement reference to its key, or null when it cannot be
     * resolved (unknown slug / null input).
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

        return $this->modelClass()::query()
            ->where('slug', $placement)
            ->value('id');
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

    /**
     * @return class-string<Placement>
     */
    private function modelClass(): string
    {
        /** @var class-string<Placement> $model */
        $model = config('advertisements.placement_model', Placement::class);

        return $model;
    }
}
