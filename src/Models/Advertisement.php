<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RoundlyConsulting\Advertisements\Casts\MoneyCast;
use RoundlyConsulting\Advertisements\Database\Factories\AdvertisementFactory;
use RoundlyConsulting\Advertisements\Enums\AdvertisementStatus;
use RoundlyConsulting\Advertisements\Support\PlacementResolver;
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
 * @property int $impressions_count
 * @property int $clicks_count
 * @property AdvertisementStatus $status
 * @property ?CarbonInterface $published_at
 * @property ?CarbonInterface $expires_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property ?Carbon $deleted_at
 *
 * @method static Builder<static> published()
 * @method static Builder<static> active()
 * @method static Builder<static> scheduled()
 * @method static Builder<static> expired()
 * @method static Builder<static> draft()
 * @method static Builder<static> archived()
 * @method static Builder<static> forAuthor(Model $author)
 * @method static Builder<static> forPlacement(Placement|int|string $placement)
 */
class Advertisement extends Model
{
    /** @use HasFactory<AdvertisementFactory> */
    use HasFactory;

    use MassPrunable;
    use SoftDeletes;

    /** @var array<string> */
    protected $guarded = [];

    /** @var array<string, int> */
    protected $attributes = [
        'impressions_count' => 0,
        'clicks_count' => 0,
    ];

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
            'impressions_count' => 'integer',
            'clicks_count' => 'integer',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsToMany<Placement, $this> */
    public function placements(): BelongsToMany
    {
        return $this->belongsToMany(Placement::class)
            ->withPivot('meta')
            ->withTimestamps();
    }

    /** @return HasMany<AdvertisementEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(AdvertisementEvent::class);
    }

    /**
     * The denormalized impression count.
     */
    public function impressions(): int
    {
        return $this->impressions_count;
    }

    /**
     * The denormalized click count.
     */
    public function clicks(): int
    {
        return $this->clicks_count;
    }

    /**
     * Click-through rate (0.0–1.0); zero when there are no impressions.
     */
    public function ctr(): float
    {
        return $this->impressions_count > 0
            ? $this->clicks_count / $this->impressions_count
            : 0.0;
    }

    /**
     * Effective lifecycle status: the stored `status` column, with time-based
     * expiry overlaid so a live ad past its `expires_at` reads as Expired.
     *
     * @return Attribute<AdvertisementStatus, never>
     */
    protected function status(): Attribute
    {
        return Attribute::get(fn (mixed $value): AdvertisementStatus => $this->resolveStatus($value));
    }

    /**
     * Reconcile the stored status column with time-based expiry/scheduling.
     */
    private function resolveStatus(mixed $value): AdvertisementStatus
    {
        $stored = $value instanceof AdvertisementStatus
            ? $value
            : AdvertisementStatus::from((string) ($value ?? AdvertisementStatus::Draft->value));

        return match (true) {
            $stored === AdvertisementStatus::Archived => AdvertisementStatus::Archived,
            $this->expires_at !== null && $this->expires_at->lessThanOrEqualTo(now()) => AdvertisementStatus::Expired,
            $this->published_at !== null && $this->published_at->isFuture() => AdvertisementStatus::Scheduled,
            $this->published_at !== null => AdvertisementStatus::Published,
            default => AdvertisementStatus::Draft,
        };
    }

    /** @param  Builder<static>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where('status', '!=', AdvertisementStatus::Archived->value);
    }

    /** @param  Builder<static>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->published()
            ->where(function (Builder $query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    /** @param  Builder<static>  $query */
    public function scopeScheduled(Builder $query): void
    {
        $query->whereNotNull('published_at')
            ->where('published_at', '>', now())
            ->where('status', '!=', AdvertisementStatus::Archived->value);
    }

    /** @param  Builder<static>  $query */
    public function scopeExpired(Builder $query): void
    {
        $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->where('status', '!=', AdvertisementStatus::Archived->value);
    }

    /** @param  Builder<static>  $query */
    public function scopeDraft(Builder $query): void
    {
        $query->whereNull('published_at')
            ->where('status', '!=', AdvertisementStatus::Archived->value);
    }

    /** @param  Builder<static>  $query */
    public function scopeArchived(Builder $query): void
    {
        $query->where('status', AdvertisementStatus::Archived->value);
    }

    /** @param  Builder<static>  $query */
    public function scopeForAuthor(Builder $query, Model $author): void
    {
        $query->where('author_type', $author->getMorphClass())
            ->where('author_id', $author->getKey());
    }

    /**
     * Constrain to ads attached to the given placement (model, id, or slug).
     *
     * @param  Builder<static>  $query
     */
    public function scopeForPlacement(Builder $query, Placement|int|string $placement): void
    {
        $key = app(PlacementResolver::class)->resolveKey($placement);

        $query->whereHas('placements', function (Builder $query) use ($key): void {
            $query->whereKey($key);
        });
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
