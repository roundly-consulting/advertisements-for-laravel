<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Testing;

use Carbon\CarbonInterface;
use Closure;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Advertisements\AdvertisementManager;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\DataTransferObjects\ImpressionData;
use RoundlyConsulting\Advertisements\Enums\AdvertisementEventType;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\Advertisements\Models\Placement;

/**
 * The recording stand-in {@see Advertisements::fake()} swaps in — a subtype of the
 * manager, so constructor-injected managers receive it too.
 *
 * Lifecycle (create, update, publish, unpublish, expire, archive, delete) and placement
 * changes still run against the database, so reads and events behave normally; each is
 * recorded, from wherever it came — the facade, an injected manager, a
 * `for($ad)->placements()` accessor or an `Advertisement` model method (`$ad->publish()`).
 *
 * Tracking (`for($ad)->track()->impression()/click()`) is recorded in memory only: no
 * event row, no counter increment, no queued job. It returns null, as buffered tracking
 * does. The placement scope is still enforced.
 *
 * This class lives in src/ so host apps can use it; it depends on PHPUnit's Assert, which is
 * always present in a Laravel app's dev dependencies.
 */
final class AdvertisementsFake extends AdvertisementManager
{
    /** @var array<string, list<Advertisement>> */
    private array $lifecycle = [];

    /** @var array<string, list<RecordedPlacements>> */
    private array $placementChanges = [];

    /** @var list<RecordedEvent> */
    private array $tracked = [];

    public function create(AdvertisementData $data): Advertisement
    {
        return $this->lifecycle('created', parent::create($data));
    }

    public function update(Advertisement $advertisement, AdvertisementData $data): Advertisement
    {
        return $this->lifecycle('updated', parent::update($advertisement, $data));
    }

    public function publish(Advertisement $advertisement, ?CarbonInterface $at = null): Advertisement
    {
        return $this->lifecycle('published', parent::publish($advertisement, $at));
    }

    public function unpublish(Advertisement $advertisement): Advertisement
    {
        return $this->lifecycle('unpublished', parent::unpublish($advertisement));
    }

    public function expire(Advertisement $advertisement, ?CarbonInterface $at = null): Advertisement
    {
        return $this->lifecycle('expired', parent::expire($advertisement, $at));
    }

    public function archive(Advertisement $advertisement): Advertisement
    {
        return $this->lifecycle('archived', parent::archive($advertisement));
    }

    public function delete(Advertisement $advertisement): bool
    {
        $deleted = parent::delete($advertisement);

        $this->lifecycle('deleted', $advertisement);

        return $deleted;
    }

    public function attachPlacementsTo(Advertisement $advertisement, iterable $placements): Advertisement
    {
        $placements = self::listOf($placements);

        return $this->placementChange('attached', parent::attachPlacementsTo($advertisement, $placements), $placements);
    }

    public function detachPlacementsFrom(Advertisement $advertisement, iterable $placements): Advertisement
    {
        $placements = self::listOf($placements);

        return $this->placementChange('detached', parent::detachPlacementsFrom($advertisement, $placements), $placements);
    }

    public function syncPlacementsOf(Advertisement $advertisement, iterable $placements): Advertisement
    {
        $placements = self::listOf($placements);

        return $this->placementChange('synced', parent::syncPlacementsOf($advertisement, $placements), $placements);
    }

    public function trackImpression(
        Advertisement $advertisement,
        Placement|int|string|null $placement = null,
        ?ImpressionData $data = null,
    ): ?AdvertisementEvent {
        $this->tracked[] = new RecordedEvent($advertisement, AdvertisementEventType::Impression, $placement, $data);

        return null;
    }

    public function trackClick(
        Advertisement $advertisement,
        Placement|int|string|null $placement = null,
        ?ImpressionData $data = null,
    ): ?AdvertisementEvent {
        $this->tracked[] = new RecordedEvent($advertisement, AdvertisementEventType::Click, $placement, $data);

        return null;
    }

    /**
     * @param  Advertisement|(Closure(Advertisement): bool)|null  $match
     */
    public function assertCreated(Advertisement|Closure|null $match = null): void
    {
        $this->assertLifecycle('created', $match);
    }

    public function assertNothingCreated(): void
    {
        $this->assertNoLifecycle('created');
    }

    /**
     * @param  Advertisement|(Closure(Advertisement): bool)|null  $match
     */
    public function assertUpdated(Advertisement|Closure|null $match = null): void
    {
        $this->assertLifecycle('updated', $match);
    }

    public function assertNothingUpdated(): void
    {
        $this->assertNoLifecycle('updated');
    }

    /**
     * @param  Advertisement|(Closure(Advertisement): bool)|null  $match
     */
    public function assertPublished(Advertisement|Closure|null $match = null): void
    {
        $this->assertLifecycle('published', $match);
    }

    public function assertNothingPublished(): void
    {
        $this->assertNoLifecycle('published');
    }

    /**
     * @param  Advertisement|(Closure(Advertisement): bool)|null  $match
     */
    public function assertUnpublished(Advertisement|Closure|null $match = null): void
    {
        $this->assertLifecycle('unpublished', $match);
    }

    public function assertNothingUnpublished(): void
    {
        $this->assertNoLifecycle('unpublished');
    }

    /**
     * @param  Advertisement|(Closure(Advertisement): bool)|null  $match
     */
    public function assertExpired(Advertisement|Closure|null $match = null): void
    {
        $this->assertLifecycle('expired', $match);
    }

    public function assertNothingExpired(): void
    {
        $this->assertNoLifecycle('expired');
    }

    /**
     * @param  Advertisement|(Closure(Advertisement): bool)|null  $match
     */
    public function assertArchived(Advertisement|Closure|null $match = null): void
    {
        $this->assertLifecycle('archived', $match);
    }

    public function assertNothingArchived(): void
    {
        $this->assertNoLifecycle('archived');
    }

    /**
     * @param  Advertisement|(Closure(Advertisement): bool)|null  $match
     */
    public function assertDeleted(Advertisement|Closure|null $match = null): void
    {
        $this->assertLifecycle('deleted', $match);
    }

    public function assertNothingDeleted(): void
    {
        $this->assertNoLifecycle('deleted');
    }

    /**
     * @param  Advertisement|(Closure(RecordedPlacements): bool)|null  $match
     */
    public function assertPlacementsAttached(Advertisement|Closure|null $match = null): void
    {
        $this->assertPlacementChange('attached', $match);
    }

    public function assertNothingAttached(): void
    {
        $this->assertNoPlacementChange('attached');
    }

    /**
     * @param  Advertisement|(Closure(RecordedPlacements): bool)|null  $match
     */
    public function assertPlacementsDetached(Advertisement|Closure|null $match = null): void
    {
        $this->assertPlacementChange('detached', $match);
    }

    public function assertNothingDetached(): void
    {
        $this->assertNoPlacementChange('detached');
    }

    /**
     * @param  Advertisement|(Closure(RecordedPlacements): bool)|null  $match
     */
    public function assertPlacementsSynced(Advertisement|Closure|null $match = null): void
    {
        $this->assertPlacementChange('synced', $match);
    }

    public function assertNothingSynced(): void
    {
        $this->assertNoPlacementChange('synced');
    }

    /**
     * @param  Advertisement|(Closure(RecordedEvent): bool)|null  $match
     */
    public function assertImpressionRecorded(Advertisement|Closure|null $match = null): void
    {
        $this->assertTracked(AdvertisementEventType::Impression, $match);
    }

    /**
     * @param  Advertisement|(Closure(RecordedEvent): bool)|null  $match
     */
    public function assertClickRecorded(Advertisement|Closure|null $match = null): void
    {
        $this->assertTracked(AdvertisementEventType::Click, $match);
    }

    /**
     * No impression and no click was tracked.
     */
    public function assertNothingRecorded(): void
    {
        Assert::assertCount(0, $this->tracked, 'Expected no tracking events to be recorded, but some were.');
    }

    private function lifecycle(string $verb, Advertisement $advertisement): Advertisement
    {
        $this->lifecycle[$verb][] = $advertisement;

        return $advertisement;
    }

    /**
     * @param  list<Placement|int|string>  $placements
     */
    private function placementChange(string $verb, Advertisement $advertisement, array $placements): Advertisement
    {
        $this->placementChanges[$verb][] = new RecordedPlacements($advertisement, $placements);

        return $advertisement;
    }

    /**
     * @param  iterable<int, Placement|int|string>  $placements
     * @return list<Placement|int|string>
     */
    private static function listOf(iterable $placements): array
    {
        $list = [];

        foreach ($placements as $placement) {
            $list[] = $placement;
        }

        return $list;
    }

    /**
     * @param  Advertisement|(Closure(Advertisement): bool)|null  $match
     */
    private function assertLifecycle(string $verb, Advertisement|Closure|null $match): void
    {
        $matches = array_filter(
            $this->lifecycle[$verb] ?? [],
            static fn (Advertisement $ad): bool => match (true) {
                $match === null => true,
                $match instanceof Advertisement => $ad->is($match),
                default => $match($ad) === true,
            },
        );

        Assert::assertNotEmpty($matches, "Expected an advertisement to be {$verb} matching the given criteria, but none was.");
    }

    private function assertNoLifecycle(string $verb): void
    {
        Assert::assertCount(0, $this->lifecycle[$verb] ?? [], "Expected no advertisement to be {$verb}, but some were.");
    }

    /**
     * @param  Advertisement|(Closure(RecordedPlacements): bool)|null  $match
     */
    private function assertPlacementChange(string $verb, Advertisement|Closure|null $match): void
    {
        $matches = array_filter(
            $this->placementChanges[$verb] ?? [],
            static fn (RecordedPlacements $change): bool => match (true) {
                $match === null => true,
                $match instanceof Advertisement => $change->ad->is($match),
                default => $match($change) === true,
            },
        );

        Assert::assertNotEmpty($matches, "Expected placements to be {$verb} matching the given criteria, but none were.");
    }

    private function assertNoPlacementChange(string $verb): void
    {
        Assert::assertCount(0, $this->placementChanges[$verb] ?? [], "Expected no placements to be {$verb}, but some were.");
    }

    /**
     * @param  Advertisement|(Closure(RecordedEvent): bool)|null  $match
     */
    private function assertTracked(AdvertisementEventType $type, Advertisement|Closure|null $match): void
    {
        $matches = array_filter(
            $this->tracked,
            static fn (RecordedEvent $event): bool => $event->type === $type && match (true) {
                $match === null => true,
                $match instanceof Advertisement => $event->ad->is($match),
                default => $match($event) === true,
            },
        );

        Assert::assertNotEmpty($matches, "Expected a recorded {$type->value} matching the given criteria, but none was found.");
    }
}
