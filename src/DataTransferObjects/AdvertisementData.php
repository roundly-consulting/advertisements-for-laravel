<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\DataTransferObjects;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Advertisements\Models\Category;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Money;

/**
 * Input for creating or updating an advertisement.
 *
 * `price` is nullable to match the column: a price-less ad is built with the
 * constructor and `price: null`. The two factories always carry an amount and say
 * which unit it is in — there is deliberately no factory taking a float.
 */
final readonly class AdvertisementData
{
    /**
     * @param  Collection<array-key, mixed>|null  $meta
     */
    public function __construct(
        public string $name,
        public ?Money $price = null,
        public Category|int|string|null $category = null,
        public ?string $description = null,
        public ?Model $author = null,
        public ?Collection $meta = null,
        public ?CarbonInterface $publishedAt = null,
        public ?CarbonInterface $expiresAt = null,
    ) {}

    /**
     * Build the data from an amount in the currency's **minor** units (e.g. cents),
     * defaulting the currency to `advertisements.default_currency`.
     *
     * The amount follows `Money::ofMinor()`: an int or an integer string (leading
     * zeros normalised) — a `numeric` column read back as a string passes straight
     * through, never through an `(int)` cast.
     *
     * @param  int|numeric-string  $minor
     * @param  Collection<array-key, mixed>|null  $meta
     */
    public static function fromMinor(
        string $name,
        int|string $minor,
        Currency|string|null $currency = null,
        Category|int|string|null $category = null,
        ?string $description = null,
        ?Model $author = null,
        ?Collection $meta = null,
        ?CarbonInterface $publishedAt = null,
        ?CarbonInterface $expiresAt = null,
    ): self {
        return new self(
            name: $name,
            price: Money::ofMinor($minor, $currency ?? self::defaultCurrency()),
            category: $category,
            description: $description,
            author: $author,
            meta: $meta,
            publishedAt: $publishedAt,
            expiresAt: $expiresAt,
        );
    }

    /**
     * Build the data from a decimal amount in **major** units (e.g. `"19.99"`),
     * defaulting the currency to `advertisements.default_currency`.
     *
     * Exact: `"1.10"` is 110 cents, and an amount finer than the currency's minor
     * unit (`"19.999"` EUR) throws `RoundingNecessary` instead of being rounded.
     *
     * @param  Collection<array-key, mixed>|null  $meta
     */
    public static function fromDecimal(
        string $name,
        string|int $amount,
        Currency|string|null $currency = null,
        Category|int|string|null $category = null,
        ?string $description = null,
        ?Model $author = null,
        ?Collection $meta = null,
        ?CarbonInterface $publishedAt = null,
        ?CarbonInterface $expiresAt = null,
    ): self {
        return new self(
            name: $name,
            price: Money::ofMajor($amount, $currency ?? self::defaultCurrency()),
            category: $category,
            description: $description,
            author: $author,
            meta: $meta,
            publishedAt: $publishedAt,
            expiresAt: $expiresAt,
        );
    }

    private static function defaultCurrency(): string
    {
        /** @var string $default */
        $default = config('advertisements.default_currency', 'EUR');

        return $default;
    }
}
