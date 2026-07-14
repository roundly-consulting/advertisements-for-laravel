<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing a placement from `advertisements.placement_model`.
 *
 * The toolkit ModelResolver validates that the configured value is a real Eloquent
 * model; it cannot know it is *ours*, so anything that is not a Placement (and so
 * cannot answer the package's slug/dimension reads) falls back to the packaged model.
 */
final class PlacementModel
{
    /** @return class-string<Placement> */
    public static function class(): string
    {
        $model = ModelResolver::for('advertisements.placement_model', Placement::class);

        return is_a($model, Placement::class, true) ? $model : Placement::class;
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
