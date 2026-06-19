<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Actions;

use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Advertisements\Support\PlacementResolver;

final class SyncPlacements
{
    public function __construct(
        private readonly PlacementResolver $resolver,
    ) {}

    /**
     * @param  iterable<int, Placement|int|string>  $placements
     */
    public function execute(Advertisement $advertisement, iterable $placements): Advertisement
    {
        $advertisement->placements()->sync(
            $this->resolver->resolveKeys($placements),
        );

        return $advertisement->load('placements');
    }
}
