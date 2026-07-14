<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing an advertisement from `advertisements.model`.
 *
 * The toolkit ModelResolver validates that the configured value is a real Eloquent
 * model; it cannot know it is *ours*, so anything that is not an Advertisement (and
 * so cannot answer the package's casts, scopes, media buckets and relations) falls
 * back to the packaged model.
 */
final class AdvertisementModel
{
    /** @return class-string<Advertisement> */
    public static function class(): string
    {
        $model = ModelResolver::for('advertisements.model', Advertisement::class);

        return is_a($model, Advertisement::class, true) ? $model : Advertisement::class;
    }

    public static function new(): Advertisement
    {
        $model = self::class();

        return new $model;
    }

    /** @return Builder<Advertisement> */
    public static function query(): Builder
    {
        return self::class()::query();
    }
}
