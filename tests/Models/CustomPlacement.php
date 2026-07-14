<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Tests\Models;

use RoundlyConsulting\Advertisements\Models\Placement;

/** A host's own placement model (`advertisements.placement_model`). */
final class CustomPlacement extends Placement
{
    protected $table = 'placements';
}
