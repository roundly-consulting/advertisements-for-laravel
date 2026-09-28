<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Testing;

use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;

/**
 * A placement change captured by {@see AdvertisementsFake}: the ad and the placements
 * (as given — models, ids or slugs) it was attached to, detached from or synced with.
 */
final readonly class RecordedPlacements
{
    /**
     * @param  list<Placement|int|string>  $placements
     */
    public function __construct(
        public Advertisement $ad,
        public array $placements,
    ) {}

    /**
     * Whether the change named this placement — by model, id or slug, whichever form
     * either side used.
     */
    public function includes(Placement|int|string $placement): bool
    {
        $wanted = self::forms($placement);

        foreach ($this->placements as $given) {
            foreach (self::forms($given) as $form) {
                if (in_array($form, $wanted, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<int|string|null>
     */
    private static function forms(Placement|int|string $placement): array
    {
        return $placement instanceof Placement
            ? [$placement->getKey(), $placement->slug]
            : [$placement];
    }
}
