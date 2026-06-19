<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Testing;

use Closure;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Advertisements\AdvertisementManager;
use RoundlyConsulting\Advertisements\DataTransferObjects\ImpressionData;
use RoundlyConsulting\Advertisements\Enums\AdvertisementEventType;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;

/**
 * A test double for the AdvertisementManager that records impression and click
 * calls in memory instead of writing rows. Non-tracking calls (create, publish,
 * …) are forwarded to the real manager so the fake is drop-in.
 *
 * @mixin AdvertisementManager
 */
final class AdvertisementsFake
{
    /** @var Collection<int, RecordedEvent> */
    private Collection $recorded;

    public function __construct(
        private readonly AdvertisementManager $manager,
    ) {
        $this->recorded = new Collection;
    }

    public function recordImpression(
        Advertisement $advertisement,
        Placement|int|string|null $placement = null,
        ?ImpressionData $data = null,
    ): RecordedEvent {
        return $this->capture(AdvertisementEventType::Impression, $advertisement, $placement, $data);
    }

    public function recordClick(
        Advertisement $advertisement,
        Placement|int|string|null $placement = null,
        ?ImpressionData $data = null,
    ): RecordedEvent {
        return $this->capture(AdvertisementEventType::Click, $advertisement, $placement, $data);
    }

    /**
     * @param  (Closure(RecordedEvent): bool)|null  $callback
     */
    public function assertImpressionRecorded(?Closure $callback = null): void
    {
        $this->assertRecorded(AdvertisementEventType::Impression, $callback);
    }

    /**
     * @param  (Closure(RecordedEvent): bool)|null  $callback
     */
    public function assertClickRecorded(?Closure $callback = null): void
    {
        $this->assertRecorded(AdvertisementEventType::Click, $callback);
    }

    public function assertNothingRecorded(): void
    {
        Assert::assertCount(
            0,
            $this->recorded,
            'Expected no tracking events to be recorded, but some were.',
        );
    }

    /**
     * Forward every non-tracking call to the real manager.
     *
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->manager->{$method}(...$arguments);
    }

    private function capture(
        AdvertisementEventType $type,
        Advertisement $advertisement,
        Placement|int|string|null $placement,
        ?ImpressionData $data,
    ): RecordedEvent {
        $event = new RecordedEvent($advertisement, $type, $placement, $data);

        $this->recorded->push($event);

        return $event;
    }

    /**
     * @param  (Closure(RecordedEvent): bool)|null  $callback
     */
    private function assertRecorded(AdvertisementEventType $type, ?Closure $callback): void
    {
        $matches = $this->recorded
            ->filter(fn (RecordedEvent $event): bool => $event->type === $type)
            ->filter(fn (RecordedEvent $event): bool => $callback === null || $callback($event));

        Assert::assertTrue(
            $matches->isNotEmpty(),
            "Expected a recorded {$type->value} matching the given criteria, but none was found.",
        );
    }
}
