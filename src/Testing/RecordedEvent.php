<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Testing;

use RoundlyConsulting\Advertisements\DataTransferObjects\ImpressionData;
use RoundlyConsulting\Advertisements\Enums\AdvertisementEventType;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;

/**
 * An in-memory record of a tracking call captured by {@see AdvertisementsFake},
 * so tests can assert against impressions and clicks without touching the DB.
 */
final readonly class RecordedEvent
{
    public function __construct(
        public Advertisement $ad,
        public AdvertisementEventType $type,
        public Placement|int|string|null $placement = null,
        public ?ImpressionData $data = null,
    ) {}
}
