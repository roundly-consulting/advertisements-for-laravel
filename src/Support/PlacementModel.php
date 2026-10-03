<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing a placement from `advertisements.placement_model`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class PlacementModel
{
    /** @return class-string<Placement> */
    public static function class(): string
    {
        return ModelResolver::for('advertisements.placement_model', Placement::class);
    }

    public static function new(): Placement
    {
        $model = self::class();

        return new $model;
    }

    /** @return Builder<Placement> */
    public static function query(): Builder
    {
        return self::class()::query();
    }
}
