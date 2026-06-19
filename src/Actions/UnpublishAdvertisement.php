<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Actions;

use RoundlyConsulting\Advertisements\Enums\AdvertisementStatus;
use RoundlyConsulting\Advertisements\Events\AdvertisementUnpublished;
use RoundlyConsulting\Advertisements\Models\Advertisement;

final class UnpublishAdvertisement
{
    /**
     * Return the advertisement to draft, clearing its publish date.
     */
    public function execute(Advertisement $advertisement): Advertisement
    {
        $advertisement->published_at = null;
        $advertisement->setAttribute('status', AdvertisementStatus::Draft->value);

        $advertisement->save();

        event(new AdvertisementUnpublished($advertisement));

        return $advertisement;
    }
}
