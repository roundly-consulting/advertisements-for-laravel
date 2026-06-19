<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Actions;

use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Events\AdvertisementCreated;
use RoundlyConsulting\Advertisements\Models\Advertisement;

final class CreateAdvertisement
{
    public function execute(AdvertisementData $data): Advertisement
    {
        $advertisement = $this->newModelInstance([
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
        }

        $advertisement->save();

        event(new AdvertisementCreated($advertisement));

        return $advertisement;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function newModelInstance(array $attributes): Advertisement
    {
        /** @var class-string<Advertisement> $model */
        $model = config('advertisements.model', Advertisement::class);

        return new $model($attributes);
    }
}
