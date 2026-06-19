<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Advertisements\Actions\CreateAdvertisement;
use RoundlyConsulting\Advertisements\Actions\UpdateAdvertisement;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Models\Advertisement;

/**
 * Expressive entry point over the advertisement action classes. The actions
 * stay injectable for DI-first consumers; this manager (and the `Advertisements`
 * facade in front of it) is the fluent convenience layer.
 */
final class AdvertisementManager
{
    public function __construct(
        private readonly CreateAdvertisement $create,
        private readonly UpdateAdvertisement $update,
    ) {}

    public function create(AdvertisementData $data): Advertisement
    {
        return $this->create->execute($data);
    }

    public function update(Advertisement $advertisement, AdvertisementData $data): Advertisement
    {
        return $this->update->execute($advertisement, $data);
    }

    /**
     * A fresh query builder for the configured advertisement model, for chaining
     * the package's query scopes.
     *
     * @return Builder<Advertisement>
     */
    public function query(): Builder
    {
        /** @var class-string<Advertisement> $model */
        $model = config('advertisements.model', Advertisement::class);

        return $model::query();
    }
}
