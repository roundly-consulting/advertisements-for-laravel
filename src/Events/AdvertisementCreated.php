<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Events;

use RoundlyConsulting\Advertisements\Models\Advertisement;

class AdvertisementCreated
{
    public function __construct(public Advertisement $advertisement) {}
}
