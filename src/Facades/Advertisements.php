<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Facades;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Advertisements\AdvertisementManager;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\DataTransferObjects\ImpressionData;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Advertisements\Testing\AdvertisementsFake;
use RoundlyConsulting\Geolocation\DataTransferObjects\Location;

/**
 * @method static Advertisement create(AdvertisementData $data)
 * @method static Advertisement update(Advertisement $advertisement, AdvertisementData $data)
 * @method static Advertisement publish(Advertisement $advertisement, ?CarbonInterface $at = null)
 * @method static Advertisement unpublish(Advertisement $advertisement)
 * @method static Advertisement expire(Advertisement $advertisement, ?CarbonInterface $at = null)
 * @method static Advertisement archive(Advertisement $advertisement)
 * @method static bool delete(Advertisement $advertisement)
 * @method static Builder<Advertisement> query()
 * @method static Builder<Advertisement> active()
 * @method static ?Advertisement random(Placement|int|string|null $placement = null)
 * @method static Builder<Advertisement> for(Placement|int|string $placement)
 * @method static Builder<Advertisement> targetedFor(Placement|int|string $placement, Request|Location|null $viewer = null)
 * @method static Advertisement attachPlacements(Advertisement $advertisement, iterable<int, Placement|int|string> $placements)
 * @method static Advertisement detachPlacements(Advertisement $advertisement, iterable<int, Placement|int|string> $placements)
 * @method static Advertisement syncPlacements(Advertisement $advertisement, iterable<int, Placement|int|string> $placements)
 * @method static AdvertisementEvent|PendingDispatch recordImpression(Advertisement $advertisement, Placement|int|string|null $placement = null, ?ImpressionData $data = null)
 * @method static AdvertisementEvent|PendingDispatch recordClick(Advertisement $advertisement, Placement|int|string|null $placement = null, ?ImpressionData $data = null)
 *
 * @see AdvertisementManager
 */
final class Advertisements extends Facade
{
    /**
     * Swap the manager for a fake that records impressions and clicks in memory
     * (forwarding everything else to the real manager) and return it so tests can
     * assert on tracking without hitting the database.
     */
    public static function fake(): AdvertisementsFake
    {
        $manager = self::getFacadeRoot();

        $fake = $manager instanceof AdvertisementsFake
            ? $manager
            : new AdvertisementsFake($manager);

        self::swap($fake);
        self::getFacadeApplication()->instance(AdvertisementManager::class, $fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return AdvertisementManager::class;
    }
}
