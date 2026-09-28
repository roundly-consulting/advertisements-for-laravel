<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\HtmlString;
use RoundlyConsulting\Advertisements\AdvertisementManager;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Advertisements\Support\PlacementModel;
use RoundlyConsulting\MediaLibrary\Buckets\MediaBucket;
use RoundlyConsulting\MediaLibrary\Concerns\InteractsWithMedia;
use RoundlyConsulting\MediaLibrary\Models\Media;
use RoundlyConsulting\MediaLibrary\Variants\VariantRegistrar;

/**
 * First-class visual creatives for the bundled Advertisement model, built on
 * roundly-consulting/media-library-for-laravel.
 *
 * Each placement gets its own single-file bucket ("creative:{placement-slug}") carrying a
 * "display" variant fit to that placement's width x height, plus a generic, size-less
 * "creative" bucket used as a fallback. On top of media-library's `InteractsWithMedia` seam
 * this adds advertisement-specific readers (the creative for a placement, its URL, and the
 * rendered `<img>` / text-ad fallback).
 *
 * @mixin Model
 * @mixin HasTranslations
 */
trait HasAdvertisementMedia
{
    use InteractsWithMedia;

    /** Web image formats accepted by the creative buckets. */
    private const CREATIVE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
    ];

    public function registerMediaBuckets(): void
    {
        // Generic, size-less creative bucket — tried before the text-ad fallback.
        $this->configureCreativeBucket($this->addMediaBucket($this->fallbackCreativeBucket()), null, null);

        // One single-file bucket per placement, each with a "display" variant fit to its dims.
        foreach (PlacementModel::query()->get() as $placement) {
            $name = $this->creativeBucketName($placement);

            if ($name === $this->fallbackCreativeBucket()) {
                continue;
            }

            $this->configureCreativeBucket(
                $this->addMediaBucket($name),
                $placement->width,
                $placement->height,
            );
        }
    }

    /**
     * Attach (replacing any prior) the creative image for a placement and return the
     * persisted media row.
     */
    public function addCreative(string|UploadedFile $file, Placement|int|string $placement): Media
    {
        return $this->addMedia($file)->toMediaBucket($this->creativeBucketName($placement));
    }

    /**
     * The creative for a placement: the placement-specific image, then the generic creative
     * when fallback is enabled, else null.
     */
    public function creativeFor(Placement|int|string $placement): ?Media
    {
        $media = $this->getFirstMedia($this->creativeBucketName($placement));

        if ($media !== null) {
            return $media;
        }

        if ($this->creativeFallbackEnabled()) {
            return $this->getFirstMedia($this->fallbackCreativeBucket());
        }

        return null;
    }

    /**
     * The URL for a placement's creative (placement-specific, then generic). Returns the
     * bucket's configured fallback URL or '' when no creative is attached.
     */
    public function creativeUrl(Placement|int|string $placement, ?string $variant = null): string
    {
        $variant ??= $this->displayVariant();
        $bucket = $this->creativeBucketName($placement);

        if ($this->hasMedia($bucket)) {
            return $this->getFirstMediaUrl($bucket, $variant);
        }

        if ($this->creativeFallbackEnabled() && $this->hasMedia($this->fallbackCreativeBucket())) {
            return $this->getFirstMediaUrl($this->fallbackCreativeBucket(), $variant);
        }

        return $this->getFirstMediaUrl($bucket, $variant);
    }

    /**
     * Render the creative for a placement: a responsive `<img>` when an image creative exists,
     * otherwise the text-ad fallback built from the ad's own name/description/CTA.
     * Sugar for `Advertisements::render($this, $placement, $attributes)`.
     *
     * @param  array<string, string>  $attributes
     */
    public function renderCreative(Placement|int|string $placement, array $attributes = []): HtmlString
    {
        return app(AdvertisementManager::class)->render($this, $placement, $attributes);
    }

    public function creativeBucketName(Placement|int|string $placement): string
    {
        return $this->creativeBucketPrefix().':'.$this->creativePlacementSlug($placement);
    }

    public function fallbackCreativeBucket(): string
    {
        return (string) config('advertisements.media.fallback_bucket', 'creative');
    }

    public function displayVariant(): string
    {
        return (string) config('advertisements.media.display_variant', 'display');
    }

    private function creativeBucketPrefix(): string
    {
        return (string) config('advertisements.media.creative_bucket_prefix', 'creative');
    }

    private function creativeFallbackEnabled(): bool
    {
        return (bool) config('advertisements.media.use_fallback_bucket', true);
    }

    private function creativePlacementSlug(Placement|int|string $placement): string
    {
        if ($placement instanceof Placement) {
            return (string) $placement->slug;
        }

        if (is_int($placement)) {
            return (string) PlacementModel::query()->whereKey($placement)->value('slug');
        }

        return $placement;
    }

    private function configureCreativeBucket(MediaBucket $bucket, ?int $width, ?int $height): MediaBucket
    {
        $bucket->singleFile()->acceptsMimeTypes(self::CREATIVE_MIME_TYPES);

        $disk = config('advertisements.media.disk');

        if (is_string($disk) && $disk !== '') {
            $bucket->useDisk($disk);
        }

        $widths = config('advertisements.media.responsive_widths');
        $bucket->responsiveWidths(is_array($widths) ? $this->normalizeWidths($widths) : null);

        $variant = $this->displayVariant();

        $bucket->registerVariants(function (VariantRegistrar $registrar) use ($variant, $width, $height): void {
            $display = $registrar->add($variant);

            if ($width !== null && $height !== null) {
                $display->width($width)->height($height)->fit('cover');
            }
        });

        return $bucket;
    }

    /**
     * @param  array<array-key, mixed>  $widths
     * @return list<int>
     */
    private function normalizeWidths(array $widths): array
    {
        $clean = [];

        foreach ($widths as $width) {
            if (is_int($width) && $width > 0) {
                $clean[] = $width;
            }
        }

        return $clean;
    }
}
