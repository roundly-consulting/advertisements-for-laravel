<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing a tracking event from `advertisements.event_model`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class EventModel
{
    /** @return class-string<AdvertisementEvent> */
    public static function class(): string
    {
        return ModelResolver::for('advertisements.event_model', AdvertisementEvent::class);
    }

    public static function new(): AdvertisementEvent
    {
        $model = self::class();

        return new $model;
    }

    /** @return Builder<AdvertisementEvent> */
    public static function query(): Builder
    {
        return self::class()::query();
    }
}
