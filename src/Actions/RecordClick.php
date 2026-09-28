<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Actions;

use RoundlyConsulting\Advertisements\DataTransferObjects\ImpressionData;
use RoundlyConsulting\Advertisements\Enums\AdvertisementEventType;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Advertisements\Support\EventRecorder;

/**
 * Record one click of an ad, optionally in a placement. Returns the persisted event,
 * or null when `advertisements.tracking.buffered` queues it instead.
 *
 * The raw use case records what it is given; `Advertisements::for($ad)->track($placement)`
 * additionally refuses a placement the ad does not run in.
 */
final class RecordClick
{
    public function __construct(
        private readonly EventRecorder $recorder,
    ) {}

    public function execute(
        Advertisement $advertisement,
        Placement|int|string|null $placement = null,
        ?ImpressionData $data = null,
    ): ?AdvertisementEvent {
        return $this->recorder->record(
            $advertisement,
            AdvertisementEventType::Click,
            $placement,
            $data,
        );
    }
}
