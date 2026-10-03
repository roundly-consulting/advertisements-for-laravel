<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Support;

use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * Strict reads of the non-boolean `advertisements.*` settings. A key that is not set — absent,
 * null or blank (`''` or whitespace, a host's `KEY=`) — takes its default; a present value of the
 * wrong shape — a `match_when_unknown` typo, an array for a bucket, a junk responsive width —
 * throws {@see InvalidConfigurationException} naming the key. A typo is never swapped for the
 * default (a `match_when_unknown` typo used to read as `untargeted_only`).
 *
 * @internal
 */
final class AdvertisementsConfig
{
    public const string UNKNOWN_UNTARGETED_ONLY = 'untargeted_only';

    public const string UNKNOWN_ALL = 'all';

    /** `untargeted_only` or `all`: what a viewer of unknown location is served. */
    public static function matchWhenUnknown(): string
    {
        return Config::oneOf(
            'advertisements.geo.match_when_unknown',
            [self::UNKNOWN_UNTARGETED_ONLY, self::UNKNOWN_ALL],
            self::UNKNOWN_UNTARGETED_ONLY,
        );
    }

    public static function defaultCurrency(): string
    {
        return self::string('advertisements.default_currency', 'EUR');
    }

    /** The locale slugs fall back to; `en` when not set. */
    public static function fallbackLocale(): string
    {
        return self::optionalFallbackLocale() ?? 'en';
    }

    /** The fallback locale, or null when not set (absent, null or blank): no translation fallback. */
    public static function optionalFallbackLocale(): ?string
    {
        return self::optionalString('advertisements.fallback_locale');
    }

    public static function creativeBucketPrefix(): string
    {
        return self::string('advertisements.media.creative_bucket_prefix', 'creative');
    }

    public static function fallbackBucket(): string
    {
        return self::string('advertisements.media.fallback_bucket', 'creative');
    }

    public static function displayVariant(): string
    {
        return self::string('advertisements.media.display_variant', 'display');
    }

    public static function textAdView(): string
    {
        return self::string('advertisements.media.text_ad_view', 'advertisements::text-ad');
    }

    /** The creative disk; not set (null or blank) uses the media package's disk. */
    public static function mediaDisk(): ?string
    {
        return self::optionalString('advertisements.media.disk');
    }

    /**
     * The responsive width ladder in pixels; not set (null or blank) leaves the media package's
     * default. Each entry must be a width: a null or blank entry inside the list is junk, not a
     * 1 px width.
     *
     * @return list<int>|null
     */
    public static function responsiveWidths(): ?array
    {
        $widths = config('advertisements.media.responsive_widths');

        if (self::blank($widths)) {
            return null;
        }

        if (! is_array($widths)) {
            throw new InvalidConfigurationException('Configuration value [advertisements.media.responsive_widths] must be a list of widths, ['.get_debug_type($widths).'] given.');
        }

        $clean = [];

        foreach ($widths as $index => $width) {
            $key = "advertisements.media.responsive_widths.{$index}";

            if (self::blank($width)) {
                throw InvalidConfigurationException::notAnInteger($key, $width);
            }

            $clean[] = Config::for([$key => $width])->integer($key, 1, min: 1);
        }

        return $clean;
    }

    public static function trackingConnection(): ?string
    {
        return self::optionalString('advertisements.tracking.connection');
    }

    public static function trackingQueue(): ?string
    {
        return self::optionalString('advertisements.tracking.queue');
    }

    private static function string(string $key, string $default): string
    {
        return self::optionalString($key) ?? $default;
    }

    private static function optionalString(string $key): ?string
    {
        return self::blank(config($key)) ? null : Config::requireString($key);
    }

    /** Not set: absent, null or a blank string (`''` or whitespace — a host's `KEY=`). */
    private static function blank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
