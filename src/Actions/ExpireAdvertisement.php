<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use RoundlyConsulting\Advertisements\Events\AdvertisementExpired;
use RoundlyConsulting\Advertisements\Models\Advertisement;

final class ExpireAdvertisement
{
    /**
     * Expire the advertisement now, or at a future instant. Archive is terminal: an archived
     * ad gets the expiry date but stays archived.
     */
    public function execute(Advertisement $advertisement, ?CarbonInterface $at = null): Advertisement
    {
        $at ??= Carbon::now();

        $advertisement->expires_at = $at;

        // Snapshot what the new date gives: Expired for an instant that has passed, the
        // publish-date status for a future one (lifting an earlier `expired` snapshot) —
        // and Archived, which the accessor never overrides, for an archived ad.
        $advertisement->setAttribute('status', $advertisement->status->value);

        $advertisement->save();

        event(new AdvertisementExpired($advertisement));

        return $advertisement;
    }
}
