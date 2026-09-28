<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
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
use RoundlyConsulting\Advertisements\Contracts\CreativeRenderer;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\DataTransferObjects\ImpressionData;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Advertisements\Support\AdvertisementModel;
use RoundlyConsulting\Advertisements\Support\ViewerLocationResolver;
use RoundlyConsulting\Advertisements\Testing\AdvertisementsFake;
use RoundlyConsulting\Geolocation\DataTransferObjects\Location;

/**
 * The advertisements API and the root of the `Advertisements` facade. Inject it to use
 * the package without the facade. Every operation resolves its action from the
 * container, so a host binding for an action applies here too.
 *
 * Not final: {@see AdvertisementsFake} extends it, so code that constructor-injects
 * the manager receives the fake under `Advertisements::fake()`.
 */
class AdvertisementManager
{
    public function __construct(
        protected readonly Container $container,
    ) {}

    public function create(AdvertisementData $data): Advertisement
    {
        return $this->container->make(CreateAdvertisement::class)->execute($data);
    }

    public function update(Advertisement $advertisement, AdvertisementData $data): Advertisement
    {
        return $this->container->make(UpdateAdvertisement::class)->execute($advertisement, $data);
    }

    /**
     * Publish now, or schedule for a future instant.
     */
    public function publish(Advertisement $advertisement, ?CarbonInterface $at = null): Advertisement
    {
        return $this->container->make(PublishAdvertisement::class)->execute($advertisement, $at);
    }

    public function unpublish(Advertisement $advertisement): Advertisement
    {
        return $this->container->make(UnpublishAdvertisement::class)->execute($advertisement);
    }

    public function expire(Advertisement $advertisement, ?CarbonInterface $at = null): Advertisement
    {
        return $this->container->make(ExpireAdvertisement::class)->execute($advertisement, $at);
    }

    public function archive(Advertisement $advertisement): Advertisement
    {
        return $this->container->make(ArchiveAdvertisement::class)->execute($advertisement);
    }

    /**
     * Soft-delete the ad and fire `AdvertisementDeleted`.
     */
    public function delete(Advertisement $advertisement): bool
    {
        return $this->container->make(DeleteAdvertisement::class)->execute($advertisement);
    }

    /**
     * A fresh query builder for the configured advertisement model, for chaining
     * the package's query scopes.
     *
     * @return Builder<Advertisement>
     */
    public function query(): Builder
    {
        return AdvertisementModel::query();
    }

    /**
     * A query already constrained to active ads.
     *
     * @return Builder<Advertisement>
     */
    public function active(): Builder
    {
        return $this->query()->active();
    }

    /**
     * The active ads available in the given placement (model, id or slug): the
     * common "what's live in this zone" query, in one call.
     *
     * @return Builder<Advertisement>
     */
    public function in(Placement|int|string $placement): Builder
    {
        return $this->query()->active()->forPlacement($placement);
    }

    /**
     * The active ads in a placement that target the given viewer's location: the
     * geo-aware counterpart of `in()`. Pass a Request (or null for the current request)
     * to resolve the viewer from their IP, or an already-resolved Location. When geo
     * targeting is disabled, this is identical to `in()`.
     *
     * @return Builder<Advertisement>
     */
    public function targetedIn(
        Placement|int|string $placement,
        Request|Location|null $viewer = null,
    ): Builder {
        $query = $this->in($placement);

        if (! (bool) config('advertisements.geo.targeting_enabled', true)) {
            return $query;
        }

        return $query->targetedAt($this->container->make(ViewerLocationResolver::class)->resolve($viewer));
    }

    /**
     * A single random active ad, optionally constrained to a placement.
     */
    public function random(Placement|int|string|null $placement = null): ?Advertisement
    {
        $query = $placement === null
            ? $this->active()
            : $this->in($placement);

        return $query->inRandomOrder()->first();
    }

    /**
     * One ad's scoped API: its placements and its tracking.
     */
    public function for(Advertisement $advertisement): AdvertisementHandle
    {
        return new AdvertisementHandle($this, $advertisement);
    }

    /**
     * Render the ad's creative for a placement: its responsive `<img>` when an image
     * creative exists, otherwise the text-ad fallback. Rendering goes through the
     * bound {@see CreativeRenderer}.
     *
     * @param  array<string, string>  $attributes
     */
    public function render(Advertisement $advertisement, Placement|int|string $placement, array $attributes = []): HtmlString
    {
        return $this->container->make(CreativeRenderer::class)->render($advertisement, $placement, $attributes);
    }

    /**
     * @internal Behind `for($ad)->placements()->attach()` — the fake's override point.
     *
     * @param  iterable<int, Placement|int|string>  $placements
     */
    public function attachPlacementsTo(Advertisement $advertisement, iterable $placements): Advertisement
    {
        return $this->container->make(AttachPlacements::class)->execute($advertisement, $placements);
    }

    /**
     * @internal Behind `for($ad)->placements()->detach()` — the fake's override point.
     *
     * @param  iterable<int, Placement|int|string>  $placements
     */
    public function detachPlacementsFrom(Advertisement $advertisement, iterable $placements): Advertisement
    {
        return $this->container->make(DetachPlacements::class)->execute($advertisement, $placements);
    }

    /**
     * @internal Behind `for($ad)->placements()->sync()` — the fake's override point.
     *
     * @param  iterable<int, Placement|int|string>  $placements
     */
    public function syncPlacementsOf(Advertisement $advertisement, iterable $placements): Advertisement
    {
        return $this->container->make(SyncPlacements::class)->execute($advertisement, $placements);
    }

    /**
     * @internal Behind `for($ad)->track()->impression()` — the fake's override point.
     *
     * The persisted event, or null when tracking is buffered to the queue.
     */
    public function trackImpression(
        Advertisement $advertisement,
        Placement|int|string|null $placement = null,
        ?ImpressionData $data = null,
    ): ?AdvertisementEvent {
        return $this->container->make(RecordImpression::class)->execute($advertisement, $placement, $data);
    }

    /**
     * @internal Behind `for($ad)->track()->click()` — the fake's override point.
     *
     * The persisted event, or null when tracking is buffered to the queue.
     */
    public function trackClick(
        Advertisement $advertisement,
        Placement|int|string|null $placement = null,
        ?ImpressionData $data = null,
    ): ?AdvertisementEvent {
        return $this->container->make(RecordClick::class)->execute($advertisement, $placement, $data);
    }
}
