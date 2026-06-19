<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Advertisements\Exceptions\InvalidPrice;
use RoundlyConsulting\Advertisements\ValueObjects\Money;

/**
 * Casts the `price` (minor units) and `currency` columns to and from a Money
 * value object.
 *
 * @implements CastsAttributes<Money|null, mixed>
 */
final class MoneyCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        $amount = $attributes['price'] ?? null;
        $currency = $attributes['currency'] ?? null;

        if ($amount === null || $currency === null) {
            return null;
        }

        return new Money((int) $amount, (string) $currency);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return ['price' => null, 'currency' => null];
        }

        if (! $value instanceof Money) {
            throw InvalidPrice::mustBeMoneyInstance();
        }

        return [
            'price' => $value->getAmount(),
            'currency' => $value->getCurrency(),
        ];
    }
}
