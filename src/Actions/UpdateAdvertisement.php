<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Actions;

use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Events\AdvertisementUpdated;
use RoundlyConsulting\Advertisements\Models\Advertisement;

final class UpdateAdvertisement
{
    public function execute(Advertisement $advertisement, AdvertisementData $data): Advertisement
    {
        $advertisement->fill([
            'name' => $data->name,
            'category' => $data->category,
            'description' => $data->description,
            'price' => $data->price,
            'meta' => $data->meta,
            'published_at' => $data->publishedAt,
            'expires_at' => $data->expiresAt,
        ]);

        if ($data->author !== null) {
            $advertisement->author()->associate($data->author);
        } else {
            $advertisement->author()->dissociate();
        }

        $advertisement->save();

        event(new AdvertisementUpdated($advertisement));

        return $advertisement;
    }
}
