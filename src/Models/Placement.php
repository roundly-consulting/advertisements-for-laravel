<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Advertisements\Concerns\HasTranslations;
use RoundlyConsulting\Advertisements\Database\Factories\PlacementFactory;
use RoundlyConsulting\Advertisements\Support\AdvertisementModel;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property ?int $width
 * @property ?int $height
 * @property ?Collection<array-key, mixed> $meta
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 *
 * Not final: `advertisements.placement_model` documents pointing this at your own
 * subclass, which `final` would forbid.
 */
class Placement extends Model
{
    /** @use HasFactory<PlacementFactory> */
    use HasFactory;

    use HasTranslations;
    use SoftDeletes;

    /** @var array<string> */
    protected $guarded = [];

    /** @var list<string> */
    public array $translatable = ['name'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'meta' => 'collection',
        ];
    }

    /**
     * The pivot table and both of its keys are named explicitly — see
     * {@see Advertisement::placements()}.
     *
     * @return BelongsToMany<Advertisement, $this>
     */
    public function advertisements(): BelongsToMany
    {
        return $this->belongsToMany(
            AdvertisementModel::class(),
            'advertisement_placement',
            'placement_id',
            'advertisement_id',
        )
            ->withPivot('meta')
            ->withTimestamps();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function newFactory(): PlacementFactory
    {
        return PlacementFactory::new();
    }
}
