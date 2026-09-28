<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements;

use RoundlyConsulting\Advertisements\DataTransferObjects\ImpressionData;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\Advertisements\Models\Placement;

/**
 * `Advertisements::for($ad)->track($placement)` — records where the ad was seen or
 * clicked. Each call returns the persisted event, or null when
 * `advertisements.tracking.buffered` queues it (and under `Advertisements::fake()`).
 */
final readonly class AdvertisementTracker
{
    public function __construct(
        private AdvertisementManager $manager,
        private Advertisement $advertisement,
        private Placement|int|string|null $placement = null,
    ) {}

    public function impression(?ImpressionData $data = null): ?AdvertisementEvent
    {
        return $this->manager->trackImpression($this->advertisement, $this->placement, $data);
    }

    public function click(?ImpressionData $data = null): ?AdvertisementEvent
    {
        return $this->manager->trackClick($this->advertisement, $this->placement, $data);
    }
}
