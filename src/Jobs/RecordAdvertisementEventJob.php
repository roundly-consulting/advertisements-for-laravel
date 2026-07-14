<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RoundlyConsulting\Advertisements\Actions\RecordAdvertisementEvent;
use RoundlyConsulting\Advertisements\DataTransferObjects\ImpressionData;
use RoundlyConsulting\Advertisements\Enums\AdvertisementEventType;
use RoundlyConsulting\Advertisements\Support\AdvertisementModel;

/**
 * Buffered tracking path: carries ids and a serialised meta payload (never whole
 * models) and replays through the shared {@see RecordAdvertisementEvent} core so
 * sync and queued recording produce the identical end state.
 */
final class RecordAdvertisementEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $advertisementId,
        public AdvertisementEventType $type,
        public int|string|null $placement = null,
        public ?ImpressionData $data = null,
    ) {}

    public function handle(RecordAdvertisementEvent $record): void
    {
        $advertisement = AdvertisementModel::query()->find($this->advertisementId);

        if ($advertisement === null) {
            return;
        }

        $record->execute($advertisement, $this->type, $this->placement, $this->data);
    }
}
