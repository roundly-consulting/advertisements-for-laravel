<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\DataTransferObjects;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Context for a recorded impression or click. All fields are optional; supplied
 * values are merged into the event's `meta` payload.
 */
final readonly class ImpressionData
{
    /**
     * @param  Collection<array-key, mixed>|null  $meta
     */
    public function __construct(
        public ?string $ip = null,
        public ?string $userAgent = null,
        public ?string $referrer = null,
        public ?CarbonInterface $occurredAt = null,
        public ?Collection $meta = null,
    ) {}

    /**
     * Merge the request context with any custom meta into a single payload for
     * the event row. Null context keys are omitted.
     *
     * @return array<string, mixed>
     */
    public function toMeta(): array
    {
        $context = array_filter([
            'ip' => $this->ip,
            'user_agent' => $this->userAgent,
            'referrer' => $this->referrer,
        ], static fn (mixed $value): bool => $value !== null);

        $custom = $this->meta?->all() ?? [];

        return [...$context, ...$custom];
    }
}
