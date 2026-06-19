<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Actions;

use RoundlyConsulting\Advertisements\Events\AdvertisementDeleted;
use RoundlyConsulting\Advertisements\Models\Advertisement;

final class DeleteAdvertisement
{
    /**
     * Soft-delete the advertisement and notify listeners.
     */
    public function execute(Advertisement $advertisement): bool
    {
        $deleted = $advertisement->performModelDelete();

        event(new AdvertisementDeleted($advertisement));

        return $deleted;
    }
}
