<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Events;

use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;

class ImpressionRecorded
{
    public function __construct(public AdvertisementEvent $event) {}
}
