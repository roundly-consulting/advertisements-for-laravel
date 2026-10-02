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
use RoundlyConsulting\Advertisements\AdvertisementManager;
use RoundlyConsulting\Advertisements\Casts\TargetingCast;
use RoundlyConsulting\Advertisements\Concerns\HasAdvertisementMedia;
use RoundlyConsulting\Advertisements\Concerns\HasTranslations;
use RoundlyConsulting\Advertisements\Database\Factories\AdvertisementFactory;
use RoundlyConsulting\Advertisements\Enums\AdvertisementEventType;
use RoundlyConsulting\Advertisements\Enums\AdvertisementStatus;
use RoundlyConsulting\Advertisements\Support\CategoryModel;
use RoundlyConsulting\Advertisements\Support\CategoryResolver;
use RoundlyConsulting\Advertisements\Support\EventModel;
use RoundlyConsulting\Advertisements\Support\PlacementModel;
use RoundlyConsulting\Advertisements\Support\PlacementResolver;
use RoundlyConsulting\Advertisements\ValueObjects\Targeting;
use RoundlyConsulting\Geolocation\DataTransferObjects\Coordinates;
use RoundlyConsulting\Geolocation\DataTransferObjects\Location;
use RoundlyConsulting\MediaLibrary\Contracts\HasMedia;
use RoundlyConsulting\Money\Casts\AsMoney;
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Sluggable\Concerns\HasSlug;
use RoundlyConsulting\Sluggable\Contracts\Sluggable;
use RoundlyConsulting\Sluggable\Definitions\SlugDefinition;
use RoundlyConsulting\Sluggable\Definitions\SlugOptions;
use RoundlyConsulting\Sluggable\Enums\EmptySourcePolicy;
use RoundlyConsulting\Sluggable\Enums\LocaleFallback;
use RoundlyConsulting\Sluggable\Enums\TargetLocales;
use RoundlyConsulting\Sluggable\Enums\UpdatePolicy;

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
 * @property ?Targeting $targeting
 * @property ?float $target_latitude
 * @property ?float $target_longitude
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
 * @method static Builder<static> targetedAt(Location|Coordinates|string|null $viewer)
 */
class Advertisement extends Model implements HasMedia, Sluggable
{
    use HasAdvertisementMedia;

    /** @use HasFactory<AdvertisementFactory> */
    use HasFactory;

    use HasSlug;
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'slug' => 'array',
            'meta' => 'collection',
            'targeting' => TargetingCast::class,
            'target_latitude' => 'float',
            'target_longitude' => 'float',
            'price' => AsMoney::currencyColumn('currency'),
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
        return $this->belongsTo(CategoryModel::class(), 'category_id');
    }

    /**
     * The pivot table and both of its keys are named explicitly: Eloquent would
     * otherwise derive them from the two CLASS names, so a host that points
     * `advertisements.model`/`placement_model` at its own subclass would query a
     * pivot table (and column) that no migration ever created.
     *
     * @return BelongsToMany<Placement, $this>
     */
    public function placements(): BelongsToMany
    {
        return $this->belongsToMany(
            PlacementModel::class(),
            'advertisement_placement',
            'advertisement_id',
            'placement_id',
        )
            ->withPivot('meta')
            ->withTimestamps();
    }

    /** @return HasMany<AdvertisementEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(EventModel::class(), 'advertisement_id');
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

    /**
     * Sugar for `Advertisements::publish($this, $at)` — goes through the manager, so
     * `Advertisements::fake()` records it.
     */
    public function publish(?CarbonInterface $at = null): static
    {
        app(AdvertisementManager::class)->publish($this, $at);

        return $this;
    }

    /**
     * Sugar for `Advertisements::unpublish($this)`.
     */
    public function unpublish(): static
    {
        app(AdvertisementManager::class)->unpublish($this);

        return $this;
    }

    /**
     * Sugar for `Advertisements::expire($this, $at)`.
     */
    public function expire(?CarbonInterface $at = null): static
    {
        app(AdvertisementManager::class)->expire($this, $at);

        return $this;
    }

    /**
     * Sugar for `Advertisements::archive($this)`.
     */
    public function archive(): static
    {
        app(AdvertisementManager::class)->archive($this);

        return $this;
    }

    /**
     * Route deletion through the manager (and its delete action) so the soft-delete
     * and the AdvertisementDeleted event fire — and the fake records it — whichever
     * way the ad is deleted.
     */
    public function delete(): bool
    {
        return app(AdvertisementManager::class)->delete($this);
    }

    /**
     * The underlying Eloquent (soft-)delete, bypassing the override above so the
     * delete action can delete without recursing.
     *
     * @internal
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
     * Never object-cached: the lifecycle actions write the raw column, and the overlay
     * depends on the clock, so every read is recomputed.
     *
     * @return Attribute<AdvertisementStatus, never>
     */
    protected function status(): Attribute
    {
        return Attribute::get(fn (mixed $value): AdvertisementStatus => $this->resolveStatus($value))
            ->withoutObjectCaching();
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
     * Constrain to ads served to a viewer's location: untargeted ads (when
     * `geo.untargeted_match`) plus targeted ads whose country/radius rules the viewer
     * satisfies. A null/unresolved viewer serves only untargeted ads, or every ad, per
     * `geo.match_when_unknown`. Targeting is evaluated precisely in PHP (exact Haversine)
     * over the candidate set already constrained by the surrounding query.
     *
     * @param  Builder<static>  $query
     */
    public function scopeTargetedAt(Builder $query, Location|Coordinates|string|null $viewer): void
    {
        if ($viewer === null) {
            if (config('advertisements.geo.match_when_unknown', 'untargeted_only') === 'all') {
                return;
            }

            $query->whereNull('targeting');

            return;
        }

        $matched = (clone $query)
            ->whereNotNull('targeting')
            ->get(['id', 'targeting'])
            ->filter(fn (self $ad): bool => $ad->targeting !== null && $this->viewerMatches($ad->targeting, $viewer))
            ->modelKeys();

        $untargetedMatch = (bool) config('advertisements.geo.untargeted_match', true);

        $query->where(function (Builder $query) use ($untargetedMatch, $matched): void {
            if ($untargetedMatch) {
                $query->whereNull('targeting');
            }

            $query->orWhereIn('id', $matched);
        });
    }

    private function viewerMatches(Targeting $targeting, Location|Coordinates|string $viewer): bool
    {
        return match (true) {
            $viewer instanceof Location => $targeting->matches($viewer),
            $viewer instanceof Coordinates => $targeting->matchesCoordinates($viewer),
            default => $targeting->matchesCountry($viewer),
        };
    }

    /**
     * Recorded impressions grouped by the stamped viewer country, e.g. `['SK' => 12]`.
     *
     * @return array<string, int>
     */
    public function impressionsByCountry(): array
    {
        return $this->eventCountsByCountry(AdvertisementEventType::Impression);
    }

    /**
     * Recorded clicks grouped by the stamped viewer country.
     *
     * @return array<string, int>
     */
    public function clicksByCountry(): array
    {
        return $this->eventCountsByCountry(AdvertisementEventType::Click);
    }

    /**
     * @return array<string, int>
     */
    private function eventCountsByCountry(AdvertisementEventType $type): array
    {
        /** @var array<string, int> $counts */
        $counts = $this->events()
            ->where('type', $type->value)
            ->whereNotNull('country_code')
            ->selectRaw('country_code, count(*) as aggregate')
            ->groupBy('country_code')
            ->pluck('aggregate', 'country_code')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();

        return $counts;
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

        $root = CategoryModel::query()->find($key);

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
     * The slug is a per-locale map generated from each locale's `name`. It regenerates only
     * for the locales whose name changed, probes collisions within one locale (trashed ads
     * keep theirs reserved) and binds routes by the current locale, then the fallback, then
     * any locale — so a stale-locale URL still resolves.
     */
    public function slugOptions(): SlugOptions
    {
        return SlugOptions::make(
            SlugDefinition::for('slug')
                ->from('name')
                ->localized()
                ->locales(TargetLocales::Source)
                ->onUpdate(UpdatePolicy::WhenSourceChanges)
                ->whenEmptySource(EmptySourcePolicy::Skip)
                ->sequentialSuffix(start: 2)
                ->includeTrashed()
                ->fallback(LocaleFallback::Any)
                ->fallbackLocale(fn (): string => (string) config('advertisements.fallback_locale', 'en'))
                ->keepHistory((bool) config('advertisements.slugs.history', false))
                ->routeKey(),
        );
    }

    protected static function newFactory(): AdvertisementFactory
    {
        return AdvertisementFactory::new();
    }
}
