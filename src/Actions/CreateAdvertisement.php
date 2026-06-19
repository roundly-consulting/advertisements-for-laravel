<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Advertisements\Advertisement;
use RoundlyConsulting\Advertisements\Events\AdvertisementCreated;
use RoundlyConsulting\Advertisements\ValueObjects\Money;

final class CreateAdvertisement
{
    /**
     * @param  Collection<array-key, mixed>|null  $meta
     */
    public function execute(
        string $name,
        int $price,
        string $currency,
        ?string $category = null,
        ?string $description = null,
        ?Model $author = null,
        ?Collection $meta = null,
        ?Carbon $publishedAt = null,
        ?Carbon $expiresAt = null,
    ): Advertisement {
        $advertisement = $this->newModelInstance([
            'name' => $name,
            'category' => $category,
            'description' => $description,
            'price' => new Money($price, $currency),
            'meta' => $meta,
            'published_at' => $publishedAt,
            'expires_at' => $expiresAt,
        ]);

        if ($author !== null) {
            $advertisement->author()->associate($author);
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
