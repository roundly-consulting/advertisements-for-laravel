<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\ValueObjects;

use NumberFormatter;
use Stringable;
use Throwable;

/**
 * Immutable money value object: an integer amount in the currency's minor unit
 * (e.g. cents) plus an ISO 4217 currency code.
 */
final readonly class Money implements Stringable
{
    public string $currency;

    public function __construct(
        public int $amount,
        string $currency,
    ) {
        $this->currency = strtoupper($currency);
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount
            && $this->currency === $other->currency;
    }

    /**
     * Format the amount for display, converting from minor units to the major
     * unit and applying the locale's currency formatting.
     *
     * Falls back to a plain "CODE 0.00" rendering when the intl formatter cannot
     * be built for the given locale or fails to format the value.
     */
    public function format(?string $locale = null): string
    {
        try {
            $formatter = new NumberFormatter(
                $locale ?? 'en_US',
                NumberFormatter::CURRENCY,
            );

            $formatted = $formatter->formatCurrency(
                $this->amount / 100,
                $this->currency,
            );
        } catch (Throwable) {
            $formatted = false;
        }

        if ($formatted === false) {
            return sprintf('%s %0.2f', $this->currency, $this->amount / 100);
        }

        return $formatted;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
