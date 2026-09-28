<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Support;

use Illuminate\Support\Facades\Bus;
use RoundlyConsulting\Advertisements\Actions\RecordAdvertisementEvent;
use RoundlyConsulting\Advertisements\DataTransferObjects\ImpressionData;
use RoundlyConsulting\Advertisements\Enums\AdvertisementEventType;
use RoundlyConsulting\Advertisements\Jobs\RecordAdvertisementEventJob;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\Advertisements\Models\Placement;

/**
 * Routes a tracking record to the synchronous core or the queued job per the
 * `advertisements.tracking` config, so both recorders share one decision point.
 * Returns the persisted event, or null once the job is dispatched.
 *
 * @internal Building block of RecordImpression / RecordClick.
 */
final class EventRecorder
{
    public function __construct(
        private readonly RecordAdvertisementEvent $core,
        private readonly PlacementResolver $resolver,
    ) {}

    public function record(
        Advertisement $advertisement,
        AdvertisementEventType $type,
        Placement|int|string|null $placement = null,
        ?ImpressionData $data = null,
    ): ?AdvertisementEvent {
        if (! config('advertisements.tracking.buffered', false)) {
            return $this->core->execute($advertisement, $type, $placement, $data);
        }

        /** @var string|null $connection */
        $connection = config('advertisements.tracking.connection');

        /** @var string|null $queue */
        $queue = config('advertisements.tracking.queue');

        Bus::dispatch((new RecordAdvertisementEventJob(
            $advertisement->getKey(),
            $type,
            $this->resolver->resolveKey($placement),
            $data,
        ))->onConnection($connection)->onQueue($queue));

        return null;
    }
}
