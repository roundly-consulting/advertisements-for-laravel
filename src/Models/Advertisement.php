<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RoundlyConsulting\Advertisements\Casts\MoneyCast;
use RoundlyConsulting\Advertisements\Database\Factories\AdvertisementFactory;
use RoundlyConsulting\Advertisements\ValueObjects\Money;

/**
 * @property int $id
 * @property ?string $author_type
 * @property ?int $author_id
 * @property string $name
 * @property ?string $slug
 * @property ?string $category
 * @property ?string $description
 * @property ?Money $price
 * @property ?string $currency
 * @property ?Collection<array-key, mixed> $meta
 * @property ?Carbon $published_at
 * @property ?Carbon $expires_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 */
class Advertisement extends Model
{
    /** @use HasFactory<AdvertisementFactory> */
    use HasFactory;

    use MassPrunable;
    use SoftDeletes;

    /** @var array<string> */
    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (Advertisement $advertisement): void {
            if ($advertisement->isDirty('name') || $advertisement->slug === null || $advertisement->slug === '') {
                $advertisement->slug = $advertisement->generateUniqueSlug();
            }
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'meta' => 'collection',
            'price' => MoneyCast::class,
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now());
    }

    protected function generateUniqueSlug(): string
    {
        $base = Str::slug((string) $this->name);
        $slug = $base;
        $suffix = 1;

        while ($this->slugExists($slug)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    protected function slugExists(string $slug): bool
    {
        return static::query()
            ->withTrashed()
            ->where('slug', $slug)
            ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()))
            ->exists();
    }

    protected static function newFactory(): AdvertisementFactory
    {
        return AdvertisementFactory::new();
    }
}
