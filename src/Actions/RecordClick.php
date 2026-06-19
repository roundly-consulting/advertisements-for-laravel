<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Actions;

use Illuminate\Foundation\Bus\PendingDispatch;
use RoundlyConsulting\Advertisements\DataTransferObjects\ImpressionData;
use RoundlyConsulting\Advertisements\Enums\AdvertisementEventType;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Advertisements\Support\EventRecorder;

final class RecordClick
{
    public function __construct(
        private readonly EventRecorder $recorder,
    ) {}

    public function execute(
        Advertisement $advertisement,
        Placement|int|string|null $placement = null,
        ?ImpressionData $data = null,
    ): AdvertisementEvent|PendingDispatch {
        return $this->recorder->record(
            $advertisement,
            AdvertisementEventType::Click,
            $placement,
            $data,
        );
    }
}
