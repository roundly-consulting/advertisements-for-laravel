<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Actions;

use RoundlyConsulting\Advertisements\Enums\AdvertisementStatus;
use RoundlyConsulting\Advertisements\Events\AdvertisementArchived;
use RoundlyConsulting\Advertisements\Models\Advertisement;

final class ArchiveAdvertisement
{
    /**
     * Archive the advertisement, taking it out of every public listing without
     * deleting it.
     */
    public function execute(Advertisement $advertisement): Advertisement
    {
        $advertisement->setAttribute('status', AdvertisementStatus::Archived->value);

        $advertisement->save();

        event(new AdvertisementArchived($advertisement));

        return $advertisement;
    }
}
