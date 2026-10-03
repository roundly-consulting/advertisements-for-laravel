<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing an advertisement from `advertisements.model`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class AdvertisementModel
{
    /** @return class-string<Advertisement> */
    public static function class(): string
    {
        return ModelResolver::for('advertisements.model', Advertisement::class);
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
