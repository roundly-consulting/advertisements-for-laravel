<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Tests\Models;

use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Testing\Fixtures\Concerns\CountsCreations;

/** A host's own placement model (`advertisements.placement_model`). */
final class CustomPlacement extends Placement
{
    use CountsCreations;

    protected $table = 'placements';
}
