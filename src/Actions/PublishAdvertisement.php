<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Advertisements\Enums\AdvertisementStatus;
use RoundlyConsulting\Advertisements\Events\AdvertisementPublished;
use RoundlyConsulting\Advertisements\Models\Advertisement;

final class PublishAdvertisement
{
    /**
     * Publish the advertisement now, or schedule it for a future instant.
     */
    public function execute(Advertisement $advertisement, ?CarbonInterface $at = null): Advertisement
    {
        $at ??= Carbon::now();

        $advertisement->published_at = $at;
        $advertisement->setAttribute('status', ($at->isFuture()
            ? AdvertisementStatus::Scheduled
            : AdvertisementStatus::Published)->value);

        $advertisement->save();

        event(new AdvertisementPublished($advertisement));

        return $advertisement;
    }
}
