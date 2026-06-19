<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\ValueObjects;

use NumberFormatter;
use RoundlyConsulting\Advertisements\Exceptions\InvalidPrice;
use Stringable;

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
     * Add another amount in the same currency, returning a new Money.
     *
     * @throws InvalidPrice when the currencies differ.
     */
    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount + $other->amount, $this->currency);
    }

    /**
     * Subtract another amount in the same currency, returning a new Money.
     *
     * @throws InvalidPrice when the currencies differ.
     */
    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function isZero(): bool
    {
        return $this->amount === 0;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw InvalidPrice::currencyMismatch($this->currency, $other->currency);
        }
    }

    /**
     * Format the amount for display, converting from minor units to the major
     * unit and applying the locale's currency formatting.
     *
     * Falls back to a plain "CODE 0.00" rendering when the intl formatter cannot
     * format the value (e.g. a currency code that is not a valid ISO 4217 code).
     */
    public function format(?string $locale = null): string
    {
        $formatter = new NumberFormatter(
            $locale ?? 'en_US',
            NumberFormatter::CURRENCY,
        );

        $formatted = $formatter->formatCurrency(
            $this->amount / 100,
            $this->currency,
        );

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
