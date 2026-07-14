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
use RoundlyConsulting\Advertisements\Support\CategoryModel;

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
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
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
        return $this->hasMany(AdvertisementModel::class());
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

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function newFactory(): CategoryFactory
    {
        return CategoryFactory::new();
    }
}
