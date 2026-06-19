<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Facades;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Advertisements\AdvertisementManager;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Models\Advertisement;

/**
 * @method static Advertisement create(AdvertisementData $data)
 * @method static Advertisement update(Advertisement $advertisement, AdvertisementData $data)
 * @method static Advertisement publish(Advertisement $advertisement, ?CarbonInterface $at = null)
 * @method static Advertisement unpublish(Advertisement $advertisement)
 * @method static Advertisement expire(Advertisement $advertisement, ?CarbonInterface $at = null)
 * @method static Advertisement archive(Advertisement $advertisement)
 * @method static bool delete(Advertisement $advertisement)
 * @method static Builder<Advertisement> query()
 *
 * @see AdvertisementManager
 */
final class Advertisements extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AdvertisementManager::class;
    }
}
