<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Advertisements\DataTransferObjects\ImpressionData;
use RoundlyConsulting\Advertisements\Enums\AdvertisementEventType;
use RoundlyConsulting\Advertisements\Events\ClickRecorded;
use RoundlyConsulting\Advertisements\Events\ImpressionRecorded;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Advertisements\Support\EventModel;
use RoundlyConsulting\Advertisements\Support\PlacementResolver;
use RoundlyConsulting\Advertisements\Support\ViewerLocationResolver;
use RoundlyConsulting\Geolocation\DataTransferObjects\Location;

/**
 * The single code path that persists a tracking event. Both the synchronous and
 * the buffered/queued recorders delegate here, so the event row, the
 * denormalized counter, and the dispatched domain event stay consistent.
 */
final class RecordAdvertisementEvent
{
    public function __construct(
        private readonly PlacementResolver $resolver,
        private readonly ViewerLocationResolver $viewerLocation,
    ) {}

    public function execute(
        Advertisement $advertisement,
        AdvertisementEventType $type,
        Placement|int|string|null $placement = null,
        ?ImpressionData $data = null,
    ): AdvertisementEvent {
        $event = $this->newEventInstance();

        $event->advertisement_id = $advertisement->getKey();
        $event->placement_id = $this->resolver->resolveKey($placement);
        $event->setAttribute('type', $type->value);
        $event->occurred_at = $data !== null && $data->occurredAt !== null
            ? $data->occurredAt
            : Carbon::now();

        $meta = $data?->toMeta() ?? [];
        $location = $this->resolveLocation($data);

        // Only a meaningful location (a resolved country) is stamped; the geolocation
        // default/last-resort provider yields an empty country we treat as unresolved.
        if ($location instanceof Location && $location->countryIsoCode !== '') {
            $event->country_code = $location->countryIsoCode;
            $meta = [...$meta, ...$this->locationMeta($location)];
        }

        $event->meta = new Collection($meta);
        $event->save();

        // increment() is a single atomic statement, so concurrent records do not
        // clobber the counter.
        $advertisement->increment($type->counterColumn());

        event($type === AdvertisementEventType::Impression
            ? new ImpressionRecorded($event)
            : new ClickRecorded($event));

        return $event;
    }

    /**
     * Resolve the viewer's location from the impression IP for geo stamping, when enabled.
     * Resolution failure (or no IP) returns null and never aborts the record.
     */
    private function resolveLocation(?ImpressionData $data): ?Location
    {
        if (! (bool) config('advertisements.geo.stamp_events', true)) {
            return null;
        }

        if ($data === null || $data->ip === null || $data->ip === '') {
            return null;
        }

        return $this->viewerLocation->resolve($data->ip);
    }

    /**
     * @return array<string, mixed>
     */
    private function locationMeta(Location $location): array
    {
        return array_filter([
            'region' => $location->region,
            'city' => $location->city,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
        ], static fn (mixed $value): bool => $value !== '');
    }

    private function newEventInstance(): AdvertisementEvent
    {
        return EventModel::new();
    }
}
