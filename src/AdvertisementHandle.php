<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements;

use RoundlyConsulting\Advertisements\Exceptions\AdvertisementNotInPlacement;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Advertisements\Support\PlacementResolver;

/**
 * One advertisement's scoped API — `Advertisements::for($ad)`: the placements it runs
 * in and the tracking of where it was seen or clicked.
 */
final readonly class AdvertisementHandle
{
    public function __construct(
        private AdvertisementManager $manager,
        private Advertisement $advertisement,
    ) {}

    /**
     * Attach, detach or sync the placements the ad runs in.
     */
    public function placements(): AdvertisementPlacements
    {
        return new AdvertisementPlacements($this->manager, $this->advertisement);
    }

    /**
     * Track the ad in a placement (or with none). A placement the ad does not run in
     * is refused — tracking is a scoped boundary, not a free-form counter.
     *
     * @throws AdvertisementNotInPlacement
     */
    public function track(Placement|int|string|null $placement = null): AdvertisementTracker
    {
        if ($placement !== null && ! $this->runsIn($placement)) {
            throw AdvertisementNotInPlacement::for($this->advertisement, $placement);
        }

        return new AdvertisementTracker($this->manager, $this->advertisement, $placement);
    }

    /**
     * Whether the ad is attached to the placement (model, id or slug).
     */
    public function runsIn(Placement|int|string $placement): bool
    {
        $key = app(PlacementResolver::class)->resolveKey($placement);

        return $key !== null && $this->advertisement->placements()->whereKey($key)->exists();
    }
}
