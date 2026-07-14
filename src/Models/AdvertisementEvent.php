<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Advertisements\Database\Factories\AdvertisementEventFactory;
use RoundlyConsulting\Advertisements\Enums\AdvertisementEventType;
use RoundlyConsulting\Advertisements\Support\AdvertisementModel;
use RoundlyConsulting\Advertisements\Support\PlacementModel;

/**
 * @property int $id
 * @property int $advertisement_id
 * @property ?int $placement_id
 * @property ?string $country_code
 * @property AdvertisementEventType $type
 * @property CarbonInterface $occurred_at
 * @property ?Collection<array-key, mixed> $meta
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 *
 * Not final: `advertisements.event_model` documents pointing this at your own
 * subclass, which `final` would forbid.
 */
class AdvertisementEvent extends Model
{
    /** @use HasFactory<AdvertisementEventFactory> */
    use HasFactory;

    /** @var array<string> */
    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => AdvertisementEventType::class,
            'occurred_at' => 'datetime',
            'meta' => 'collection',
        ];
    }

    /** @return BelongsTo<Advertisement, $this> */
    public function advertisement(): BelongsTo
    {
        return $this->belongsTo(AdvertisementModel::class(), 'advertisement_id');
    }

    /** @return BelongsTo<Placement, $this> */
    public function placement(): BelongsTo
    {
        return $this->belongsTo(PlacementModel::class(), 'placement_id');
    }

    protected static function newFactory(): AdvertisementEventFactory
    {
        return AdvertisementEventFactory::new();
    }
}
