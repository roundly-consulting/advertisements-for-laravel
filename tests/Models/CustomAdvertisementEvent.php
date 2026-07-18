<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Tests\Models;

use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\Testing\Fixtures\Concerns\CountsCreations;

/** A host's own tracking-event model (`advertisements.event_model`). */
final class CustomAdvertisementEvent extends AdvertisementEvent
{
    use CountsCreations;

    protected $table = 'advertisement_events';
}
