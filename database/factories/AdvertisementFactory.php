<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Advertisements\Enums\AdvertisementStatus;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Money\Money;

/** @extends Factory<Advertisement> */
final class AdvertisementFactory extends Factory
{
    protected $model = Advertisement::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'price' => Money::ofMinor(fake()->numberBetween(100, 10000), 'EUR'),
            'status' => AdvertisementStatus::Draft->value,
        ];
    }

    public function published(): self
    {
        return $this->state(fn (array $attributes): array => [
            'published_at' => now()->subDay(),
            'status' => AdvertisementStatus::Published->value,
        ]);
    }

    public function scheduled(): self
    {
        return $this->state(fn (array $attributes): array => [
            'published_at' => now()->addDay(),
            'status' => AdvertisementStatus::Scheduled->value,
        ]);
    }

    public function expired(): self
    {
        return $this->state(fn (array $attributes): array => [
            'published_at' => now()->subDays(2),
            'expires_at' => now()->subDay(),
            'status' => AdvertisementStatus::Published->value,
        ]);
    }

    public function archived(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AdvertisementStatus::Archived->value,
        ]);
    }
}
