<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Events;

use RoundlyConsulting\Advertisements\Advertisement;

class AdvertisementUpdated
{
    public function __construct(public Advertisement $advertisement) {}
}
