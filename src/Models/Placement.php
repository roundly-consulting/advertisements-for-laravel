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
use RoundlyConsulting\Advertisements\Support\AdvertisementsConfig;
use RoundlyConsulting\Sluggable\Concerns\HasSlug;
use RoundlyConsulting\Sluggable\Contracts\Sluggable;
use RoundlyConsulting\Sluggable\Definitions\SlugDefinition;
use RoundlyConsulting\Sluggable\Definitions\SlugOptions;
use RoundlyConsulting\Sluggable\Enums\ManualSlugPolicy;
use RoundlyConsulting\Sluggable\Enums\SlugStorage;

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
class Placement extends Model implements Sluggable
{
    /** @use HasFactory<PlacementFactory> */
    use HasFactory;

    use HasSlug;
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

    /**
     * A placement slug is a code-facing key (`'sidebar'`) and the storage key of every creative
     * uploaded for it (`creative:{slug}`), so it must never drift: generated from the
     * fallback-locale name when left empty, never changed afterwards, and a manual value is
     * kept byte-for-byte — or rejected with SlugAlreadyTakenException when taken.
     */
    public function slugOptions(): SlugOptions
    {
        return SlugOptions::make(
            SlugDefinition::for('slug')
                ->from('name')
                ->sourceLocale(AdvertisementsConfig::fallbackLocale())
                ->storage(SlugStorage::String)
                ->immutable()
                ->manual(ManualSlugPolicy::Strict)
                ->routeKey(),
        );
    }

    protected static function newFactory(): PlacementFactory
    {
        return PlacementFactory::new();
    }
}
