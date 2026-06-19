<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RoundlyConsulting\Advertisements\Actions\ArchiveAdvertisement;
use RoundlyConsulting\Advertisements\Actions\DeleteAdvertisement;
use RoundlyConsulting\Advertisements\Actions\ExpireAdvertisement;
use RoundlyConsulting\Advertisements\Actions\PublishAdvertisement;
use RoundlyConsulting\Advertisements\Actions\UnpublishAdvertisement;
use RoundlyConsulting\Advertisements\Casts\MoneyCast;
use RoundlyConsulting\Advertisements\Concerns\HasTranslations;
use RoundlyConsulting\Advertisements\Database\Factories\AdvertisementFactory;
use RoundlyConsulting\Advertisements\Enums\AdvertisementStatus;
use RoundlyConsulting\Advertisements\Support\CategoryResolver;
use RoundlyConsulting\Advertisements\Support\PlacementResolver;
use RoundlyConsulting\Advertisements\ValueObjects\Money;

/**
 * @property int $id
 * @property ?string $author_type
 * @property ?int $author_id
 * @property string $name
 * @property ?string $slug
 * @property ?int $category_id
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
 * @method static Builder<static> inCategory(Category|int|string $category, bool $includeDescendants = false)
 */
class Advertisement extends Model
{
    /** @use HasFactory<AdvertisementFactory> */
    use HasFactory;

    use HasTranslations;
    use MassPrunable;
    use SoftDeletes;

    /** @var list<string> */
    public array $translatable = ['name', 'description', 'slug'];

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
            $advertisement->syncSlugForCurrentLocale();
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'slug' => 'array',
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

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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

    public function isPublished(): bool
    {
        return $this->status === AdvertisementStatus::Published;
    }

    public function isActive(): bool
    {
        return $this->isPublished()
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isExpired(): bool
    {
        return $this->status === AdvertisementStatus::Expired;
    }

    public function isScheduled(): bool
    {
        return $this->status === AdvertisementStatus::Scheduled;
    }

    public function isArchived(): bool
    {
        return $this->status === AdvertisementStatus::Archived;
    }

    public function publish(?CarbonInterface $at = null): static
    {
        app(PublishAdvertisement::class)->execute($this, $at);

        return $this;
    }

    public function unpublish(): static
    {
        app(UnpublishAdvertisement::class)->execute($this);

        return $this;
    }

    public function expire(?CarbonInterface $at = null): static
    {
        app(ExpireAdvertisement::class)->execute($this, $at);

        return $this;
    }

    public function archive(): static
    {
        app(ArchiveAdvertisement::class)->execute($this);

        return $this;
    }

    /**
     * Route deletion through the package action so the soft-delete and the
     * AdvertisementDeleted event fire whether the model or the action is called.
     */
    public function delete(): bool
    {
        return app(DeleteAdvertisement::class)->execute($this);
    }

    /**
     * The underlying Eloquent (soft-)delete, bypassing the action override so
     * {@see DeleteAdvertisement} can delete without recursing.
     */
    public function performModelDelete(): bool
    {
        return (bool) parent::delete();
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

    /**
     * Constrain to ads in the given category (model, id, or slug). With
     * $includeDescendants, nested categories are included too.
     *
     * @param  Builder<static>  $query
     */
    public function scopeInCategory(Builder $query, Category|int|string $category, bool $includeDescendants = false): void
    {
        $key = app(CategoryResolver::class)->resolveKey($category);

        // An unresolvable reference (e.g. unknown slug) matches no ads rather than
        // every ad with a null category_id.
        if ($key === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        if (! $includeDescendants) {
            $query->where('category_id', $key);

            return;
        }

        /** @var class-string<Category> $model */
        $model = config('advertisements.category_model', Category::class);
        $root = $model::query()->find($key);

        $ids = [$key];

        if ($root !== null) {
            $ids = [...$ids, ...$root->descendants()->modelKeys()];
        }

        $query->whereIn('category_id', $ids);
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now());
    }

    /**
     * Generate the current locale's slug from that locale's name (or the resolved
     * name fallback) when the name changed or the slug is missing. Other locales'
     * slugs are left untouched.
     */
    protected function syncSlugForCurrentLocale(): void
    {
        $locale = app()->getLocale();
        $slugs = $this->getTranslations('slug');
        $currentSlug = $slugs[$locale] ?? null;

        $nameDirty = $this->isDirty('name');

        if (! $nameDirty && $currentSlug !== null && $currentSlug !== '') {
            return;
        }

        $source = $this->getTranslation('name', $locale) ?? $this->getTranslation('name');

        if ($source === null || $source === '') {
            return;
        }

        $this->setTranslation('slug', $locale, $this->generateUniqueSlug($source, $locale));
    }

    protected function generateUniqueSlug(string $source, string $locale): string
    {
        $base = Str::slug($source);
        $slug = $base;
        $suffix = 1;

        while ($this->slugExists($slug, $locale)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * Slug uniqueness is per locale: two ads may share a slug across locales but
     * not within one. The JSON path query resolves portably across SQLite,
     * MySQL, and Postgres (Laravel emits `json_extract`).
     */
    protected function slugExists(string $slug, string $locale): bool
    {
        return static::query()
            ->withTrashed()
            ->where('slug->'.$locale, $slug)
            ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()))
            ->exists();
    }

    protected static function newFactory(): AdvertisementFactory
    {
        return AdvertisementFactory::new();
    }
}
