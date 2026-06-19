<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Advertisements\Advertisement;
use RoundlyConsulting\Advertisements\ValueObjects\Money;

/** @extends Factory<Advertisement> */
final class AdvertisementFactory extends Factory
{
    protected $model = Advertisement::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'category' => fake()->word(),
            'description' => fake()->sentence(),
            'price' => new Money(fake()->numberBetween(100, 10000), 'EUR'),
        ];
    }

    public function published(): self
    {
        return $this->state(fn (array $attributes): array => [
            'published_at' => now()->subDay(),
        ]);
    }

    public function expired(): self
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subDay(),
        ]);
    }
}
