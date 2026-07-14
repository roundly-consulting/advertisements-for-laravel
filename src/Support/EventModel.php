<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing a tracking event from `advertisements.event_model`.
 *
 * The toolkit ModelResolver validates that the configured value is a real Eloquent
 * model; it cannot know it is *ours*, so anything that is not an AdvertisementEvent
 * (and so cannot answer the package's type cast and relations) falls back to the
 * packaged model.
 */
final class EventModel
{
    /** @return class-string<AdvertisementEvent> */
    public static function class(): string
    {
        $model = ModelResolver::for('advertisements.event_model', AdvertisementEvent::class);

        return is_a($model, AdvertisementEvent::class, true) ? $model : AdvertisementEvent::class;
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
