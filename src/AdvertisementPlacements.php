<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements;

use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;

/**
 * `Advertisements::for($ad)->placements()` — the placements one ad runs in. Placements
 * are given as models, ids or slugs; unknown slugs are skipped. Each call returns the
 * ad with its `placements` relation reloaded.
 */
final readonly class AdvertisementPlacements
{
    public function __construct(
        private AdvertisementManager $manager,
        private Advertisement $advertisement,
    ) {}

    /**
     * Add placements, keeping the ones already attached.
     *
     * @param  iterable<int, Placement|int|string>  $placements
     */
    public function attach(iterable $placements): Advertisement
    {
        return $this->manager->attachPlacementsTo($this->advertisement, $placements);
    }

    /**
     * @param  iterable<int, Placement|int|string>  $placements
     */
    public function detach(iterable $placements): Advertisement
    {
        return $this->manager->detachPlacementsFrom($this->advertisement, $placements);
    }

    /**
     * Make exactly these the ad's placements.
     *
     * @param  iterable<int, Placement|int|string>  $placements
     */
    public function sync(iterable $placements): Advertisement
    {
        return $this->manager->syncPlacementsOf($this->advertisement, $placements);
    }
}
