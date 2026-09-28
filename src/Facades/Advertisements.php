<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Facades;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\HtmlString;
use RoundlyConsulting\Advertisements\AdvertisementHandle;
use RoundlyConsulting\Advertisements\AdvertisementManager;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Models\Advertisement;
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
 * @method static Builder<Advertisement> in(Placement|int|string $placement)
 * @method static Builder<Advertisement> targetedIn(Placement|int|string $placement, Request|Location|null $viewer = null)
 * @method static ?Advertisement random(Placement|int|string|null $placement = null)
 * @method static AdvertisementHandle for(Advertisement $advertisement)
 * @method static HtmlString render(Advertisement $advertisement, Placement|int|string $placement, array<string, string> $attributes = [])
 *
 * @see AdvertisementManager
 */
final class Advertisements extends Facade
{
    /**
     * Swap in a recording fake — behind the facade and in the container, so injected
     * managers and `Advertisement` model methods hit it too. Lifecycle and placement
     * changes still run; tracking is recorded in memory only. Calling it again returns
     * the fake already installed.
     */
    public static function fake(): AdvertisementsFake
    {
        $current = self::getFacadeRoot();

        $fake = $current instanceof AdvertisementsFake
            ? $current
            : self::getFacadeApplication()->make(AdvertisementsFake::class);

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return AdvertisementManager::class;
    }
}
