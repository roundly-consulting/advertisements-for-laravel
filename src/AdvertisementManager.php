<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\PendingDispatch;
use RoundlyConsulting\Advertisements\Actions\ArchiveAdvertisement;
use RoundlyConsulting\Advertisements\Actions\AttachPlacements;
use RoundlyConsulting\Advertisements\Actions\CreateAdvertisement;
use RoundlyConsulting\Advertisements\Actions\DeleteAdvertisement;
use RoundlyConsulting\Advertisements\Actions\DetachPlacements;
use RoundlyConsulting\Advertisements\Actions\ExpireAdvertisement;
use RoundlyConsulting\Advertisements\Actions\PublishAdvertisement;
use RoundlyConsulting\Advertisements\Actions\RecordClick;
use RoundlyConsulting\Advertisements\Actions\RecordImpression;
use RoundlyConsulting\Advertisements\Actions\SyncPlacements;
use RoundlyConsulting\Advertisements\Actions\UnpublishAdvertisement;
use RoundlyConsulting\Advertisements\Actions\UpdateAdvertisement;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\DataTransferObjects\ImpressionData;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\Advertisements\Models\Placement;

/**
 * Expressive entry point over the advertisement action classes. The actions
 * stay injectable for DI-first consumers; this manager (and the `Advertisements`
 * facade in front of it) is the fluent convenience layer.
 */
final class AdvertisementManager
{
    public function __construct(
        private readonly CreateAdvertisement $create,
        private readonly UpdateAdvertisement $update,
        private readonly PublishAdvertisement $publish,
        private readonly UnpublishAdvertisement $unpublish,
        private readonly ExpireAdvertisement $expire,
        private readonly ArchiveAdvertisement $archive,
        private readonly DeleteAdvertisement $delete,
    ) {}

    public function create(AdvertisementData $data): Advertisement
    {
        return $this->create->execute($data);
    }

    public function update(Advertisement $advertisement, AdvertisementData $data): Advertisement
    {
        return $this->update->execute($advertisement, $data);
    }

    public function publish(Advertisement $advertisement, ?CarbonInterface $at = null): Advertisement
    {
        return $this->publish->execute($advertisement, $at);
    }

    public function unpublish(Advertisement $advertisement): Advertisement
    {
        return $this->unpublish->execute($advertisement);
    }

    public function expire(Advertisement $advertisement, ?CarbonInterface $at = null): Advertisement
    {
        return $this->expire->execute($advertisement, $at);
    }

    public function archive(Advertisement $advertisement): Advertisement
    {
        return $this->archive->execute($advertisement);
    }

    public function delete(Advertisement $advertisement): bool
    {
        return $this->delete->execute($advertisement);
    }

    /**
     * A fresh query builder for the configured advertisement model, for chaining
     * the package's query scopes.
     *
     * @return Builder<Advertisement>
     */
    public function query(): Builder
    {
        /** @var class-string<Advertisement> $model */
        $model = config('advertisements.model', Advertisement::class);

        return $model::query();
    }

    /**
     * The active ads available in the given placement: the common "what's live in
     * this zone" query, in one call.
     *
     * @return Builder<Advertisement>
     */
    public function for(Placement|int|string $placement): Builder
    {
        return $this->query()->active()->forPlacement($placement);
    }

    /**
     * @param  iterable<int, Placement|int|string>  $placements
     */
    public function attachPlacements(Advertisement $advertisement, iterable $placements): Advertisement
    {
        return app(AttachPlacements::class)->execute($advertisement, $placements);
    }

    /**
     * @param  iterable<int, Placement|int|string>  $placements
     */
    public function detachPlacements(Advertisement $advertisement, iterable $placements): Advertisement
    {
        return app(DetachPlacements::class)->execute($advertisement, $placements);
    }

    /**
     * @param  iterable<int, Placement|int|string>  $placements
     */
    public function syncPlacements(Advertisement $advertisement, iterable $placements): Advertisement
    {
        return app(SyncPlacements::class)->execute($advertisement, $placements);
    }

    /**
     * Record an impression for the ad — synchronously, or buffered to the queue
     * per the `advertisements.tracking` config.
     */
    public function recordImpression(
        Advertisement $advertisement,
        Placement|int|string|null $placement = null,
        ?ImpressionData $data = null,
    ): AdvertisementEvent|PendingDispatch {
        return app(RecordImpression::class)->execute($advertisement, $placement, $data);
    }

    /**
     * Record a click for the ad — synchronously, or buffered to the queue per the
     * `advertisements.tracking` config.
     */
    public function recordClick(
        Advertisement $advertisement,
        Placement|int|string|null $placement = null,
        ?ImpressionData $data = null,
    ): AdvertisementEvent|PendingDispatch {
        return app(RecordClick::class)->execute($advertisement, $placement, $data);
    }
}
