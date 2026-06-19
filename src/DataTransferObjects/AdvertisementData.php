<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\DataTransferObjects;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RoundlyConsulting\Advertisements\Models\Category;
use RoundlyConsulting\Advertisements\ValueObjects\Money;

/**
 * Input for creating or updating an advertisement.
 */
final readonly class AdvertisementData
{
    /**
     * @param  Collection<array-key, mixed>|null  $meta
     */
    public function __construct(
        public string $name,
        public Money $price,
        public Category|int|string|null $category = null,
        public ?string $description = null,
        public ?Model $author = null,
        public ?Collection $meta = null,
        public ?CarbonInterface $publishedAt = null,
        public ?CarbonInterface $expiresAt = null,
    ) {}

    /**
     * Build the data from a bare amount in minor units, defaulting the currency
     * to the package's configured `default_currency` when none is supplied.
     *
     * @param  Collection<array-key, mixed>|null  $meta
     */
    public static function fromAmount(
        string $name,
        int $amount,
        ?string $currency = null,
        Category|int|string|null $category = null,
        ?string $description = null,
        ?Model $author = null,
        ?Collection $meta = null,
        ?CarbonInterface $publishedAt = null,
        ?CarbonInterface $expiresAt = null,
    ): self {
        /** @var string $default */
        $default = config('advertisements.default_currency', 'EUR');

        return new self(
            name: $name,
            price: new Money($amount, $currency ?? $default),
            category: $category,
            description: $description,
            author: $author,
            meta: $meta,
            publishedAt: $publishedAt,
            expiresAt: $expiresAt,
        );
    }
}
