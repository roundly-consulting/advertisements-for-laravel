<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Actions;

use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Events\AdvertisementUpdated;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Support\CategoryResolver;

/**
 * Change the fields the data provides and keep every other one: an omitted argument leaves
 * the stored value alone, an explicit `null` clears it (see {@see AdvertisementData}).
 */
final class UpdateAdvertisement
{
    public function __construct(
        private readonly CategoryResolver $categories,
    ) {}

    public function execute(Advertisement $advertisement, AdvertisementData $data): Advertisement
    {
        $advertisement->name = $data->name;

        if ($data->provides('price')) {
            // Currency first: the price cast refuses to re-denominate a currency column that
            // already holds another code, so switching an ad from EUR to USD is explicit here.
            if ($data->price !== null) {
                $advertisement->currency = $data->price->currency()->code;
            }

            $advertisement->price = $data->price;
        }

        if ($data->provides('category')) {
            $advertisement->category_id = $this->categories->resolveKey($data->category);
        }

        if ($data->provides('description')) {
            $advertisement->description = $data->description;
        }

        if ($data->provides('meta')) {
            $advertisement->meta = $data->meta;
        }

        if ($data->provides('publishedAt')) {
            $advertisement->published_at = $data->publishedAt;
        }

        if ($data->provides('expiresAt')) {
            $advertisement->expires_at = $data->expiresAt;
        }

        // Re-snapshot the column from the (possibly moved) dates; an archived ad stays archived.
        $advertisement->setAttribute('status', $advertisement->status->value);

        if ($data->provides('author')) {
            $data->author !== null
                ? $advertisement->author()->associate($data->author)
                : $advertisement->author()->dissociate();
        }

        $advertisement->save();

        event(new AdvertisementUpdated($advertisement));

        return $advertisement;
    }
}
