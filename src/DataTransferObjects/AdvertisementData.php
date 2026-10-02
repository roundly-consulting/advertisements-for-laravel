<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\DataTransferObjects;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Advertisements\Enums\Omitted;
use RoundlyConsulting\Advertisements\Models\Category;
use RoundlyConsulting\Money\Currency;
use RoundlyConsulting\Money\Money;

/**
 * Input for creating or updating an advertisement.
 *
 * Every argument but `name` is optional, and the DTO remembers which ones the caller passed:
 * on create an omitted field is empty, on update it keeps the ad's stored value, while an
 * explicit `null` clears it. So `update($ad, new AdvertisementData(name: …, price: …))` changes
 * the name and the price and nothing else. An omitted field reads as null here.
 *
 * `price` is nullable to match the column: a price-less ad is built with the
 * constructor and `price: null`. The two factories always carry an amount and say
 * which unit it is in — there is deliberately no factory taking a float.
 */
final readonly class AdvertisementData
{
    public ?Money $price;

    public Category|int|string|null $category;

    public ?string $description;

    public ?Model $author;

    /** @var Collection<array-key, mixed>|null */
    public ?Collection $meta;

    public ?CarbonInterface $publishedAt;

    public ?CarbonInterface $expiresAt;

    /** @var list<string> the optional fields the caller passed */
    private array $given;

    /**
     * @param  Collection<array-key, mixed>|Omitted|null  $meta
     */
    public function __construct(
        public string $name,
        Money|Omitted|null $price = Omitted::Value,
        Category|int|string|Omitted|null $category = Omitted::Value,
        string|Omitted|null $description = Omitted::Value,
        Model|Omitted|null $author = Omitted::Value,
        Collection|Omitted|null $meta = Omitted::Value,
        CarbonInterface|Omitted|null $publishedAt = Omitted::Value,
        CarbonInterface|Omitted|null $expiresAt = Omitted::Value,
    ) {
        $given = [];

        foreach (compact('price', 'category', 'description', 'author', 'meta', 'publishedAt', 'expiresAt') as $field => $value) {
            if (! $value instanceof Omitted) {
                $given[] = $field;
            }
        }

        $this->given = $given;
        $this->price = $price instanceof Omitted ? null : $price;
        $this->category = $category instanceof Omitted ? null : $category;
        $this->description = $description instanceof Omitted ? null : $description;
        $this->author = $author instanceof Omitted ? null : $author;
        $this->meta = $meta instanceof Omitted ? null : $meta;
        $this->publishedAt = $publishedAt instanceof Omitted ? null : $publishedAt;
        $this->expiresAt = $expiresAt instanceof Omitted ? null : $expiresAt;
    }

    /**
     * Build the data from an amount in the currency's **minor** units (e.g. cents),
     * defaulting the currency to `advertisements.default_currency`.
     *
     * The amount follows `Money::ofMinor()`: an int or an integer string (leading
     * zeros normalised) — a `numeric` column read back as a string passes straight
     * through, never through an `(int)` cast.
     *
     * @param  int|numeric-string  $minor
     * @param  Collection<array-key, mixed>|Omitted|null  $meta
     */
    public static function fromMinor(
        string $name,
        int|string $minor,
        Currency|string|null $currency = null,
        Category|int|string|Omitted|null $category = Omitted::Value,
        string|Omitted|null $description = Omitted::Value,
        Model|Omitted|null $author = Omitted::Value,
        Collection|Omitted|null $meta = Omitted::Value,
        CarbonInterface|Omitted|null $publishedAt = Omitted::Value,
        CarbonInterface|Omitted|null $expiresAt = Omitted::Value,
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
     * @param  Collection<array-key, mixed>|Omitted|null  $meta
     */
    public static function fromDecimal(
        string $name,
        string|int $amount,
        Currency|string|null $currency = null,
        Category|int|string|Omitted|null $category = Omitted::Value,
        string|Omitted|null $description = Omitted::Value,
        Model|Omitted|null $author = Omitted::Value,
        Collection|Omitted|null $meta = Omitted::Value,
        CarbonInterface|Omitted|null $publishedAt = Omitted::Value,
        CarbonInterface|Omitted|null $expiresAt = Omitted::Value,
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

    /**
     * Whether the caller passed the optional field (by property name: `price`, `category`,
     * `description`, `author`, `meta`, `publishedAt`, `expiresAt`) — an explicit `null`
     * counts. `name` is required, so it is always passed.
     */
    public function provides(string $field): bool
    {
        return $field === 'name' || in_array($field, $this->given, true);
    }

    private static function defaultCurrency(): string
    {
        /** @var string $default */
        $default = config('advertisements.default_currency', 'EUR');

        return $default;
    }
}
