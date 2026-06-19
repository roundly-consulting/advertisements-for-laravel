<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Events;

use RoundlyConsulting\Advertisements\Models\Advertisement;

class AdvertisementPublished
{
    public function __construct(public Advertisement $advertisement) {}
}
