<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Advertisements\Events\AdvertisementUpdated;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\ValueObjects\Money;

final class UpdateAdvertisement
{
    /**
     * @param  Collection<array-key, mixed>|null  $meta
     */
    public function execute(
        Advertisement $advertisement,
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
        $advertisement->fill([
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
        } else {
            $advertisement->author()->dissociate();
        }

        $advertisement->save();

        event(new AdvertisementUpdated($advertisement));

        return $advertisement;
    }
}
