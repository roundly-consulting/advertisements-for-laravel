<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Advertisements\Enums\AdvertisementStatus;
use RoundlyConsulting\Advertisements\Events\AdvertisementExpired;
use RoundlyConsulting\Advertisements\Models\Advertisement;

final class ExpireAdvertisement
{
    /**
     * Expire the advertisement now, or at a future instant.
     */
    public function execute(Advertisement $advertisement, ?CarbonInterface $at = null): Advertisement
    {
        $at ??= Carbon::now();

        $advertisement->expires_at = $at;

        if (! $at->isFuture()) {
            $advertisement->setAttribute('status', AdvertisementStatus::Expired->value);
        }

        $advertisement->save();

        event(new AdvertisementExpired($advertisement));

        return $advertisement;
    }
}
