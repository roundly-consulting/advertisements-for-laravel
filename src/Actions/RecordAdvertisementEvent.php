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
use RoundlyConsulting\Advertisements\Support\PlacementResolver;

/**
 * The single code path that persists a tracking event. Both the synchronous and
 * the buffered/queued recorders delegate here, so the event row, the
 * denormalized counter, and the dispatched domain event stay consistent.
 */
final class RecordAdvertisementEvent
{
    public function __construct(
        private readonly PlacementResolver $resolver,
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
        $event->meta = new Collection($data?->toMeta() ?? []);
        $event->save();

        // increment() is a single atomic statement, so concurrent records do not
        // clobber the counter.
        $advertisement->increment($type->counterColumn());

        event($type === AdvertisementEventType::Impression
            ? new ImpressionRecorded($event)
            : new ClickRecorded($event));

        return $event;
    }

    private function newEventInstance(): AdvertisementEvent
    {
        /** @var class-string<AdvertisementEvent> $model */
        $model = config('advertisements.event_model', AdvertisementEvent::class);

        return new $model;
    }
}
