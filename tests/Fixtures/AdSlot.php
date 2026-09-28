<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Tests\Fixtures;

use RoundlyConsulting\Advertisements\AdvertisementManager;

/**
 * A host class that constructor-injects the manager — the shape that hit a
 * TypeError under the old, non-subtype fake.
 */
final readonly class AdSlot
{
    public function __construct(public AdvertisementManager $ads) {}
}
