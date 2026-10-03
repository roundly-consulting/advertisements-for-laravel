<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Models;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Advertisements\Concerns\HasTranslations;
use RoundlyConsulting\Advertisements\Database\Factories\CategoryFactory;
use RoundlyConsulting\Advertisements\Support\AdvertisementModel;
use RoundlyConsulting\Advertisements\Support\AdvertisementsConfig;
use RoundlyConsulting\Advertisements\Support\CategoryModel;
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
 * @property ?int $parent_id
 * @property ?Collection<array-key, mixed> $meta
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 *
 * Not final: `advertisements.category_model` documents pointing this at your own
 * subclass, which `final` would forbid.
 */
class Category extends Model implements Sluggable
{
    /** @use HasFactory<CategoryFactory> */
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

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(CategoryModel::class(), 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(CategoryModel::class(), 'parent_id');
    }

    /** @return HasMany<Advertisement, $this> */
    public function advertisements(): HasMany
    {
        return $this->hasMany(AdvertisementModel::class(), 'category_id');
    }

    /**
     * Every descendant category beneath this one (recursive).
     *
     * @return EloquentCollection<int, Category>
     */
    public function descendants(): EloquentCollection
    {
        /** @var EloquentCollection<int, Category> $descendants */
        $descendants = new EloquentCollection;

        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->descendants());
        }

        return $descendants;
    }

    /**
     * A category slug is a code-facing key (`inCategory('vehicles')`): generated from the
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

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }
}
