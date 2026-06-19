<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use RoundlyConsulting\Advertisements\Models\Placement;

/** @extends Factory<Placement> */
final class PlacementFactory extends Factory
{
    protected $model = Placement::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 100000),
            'name' => ['en' => Str::title($name)],
            'width' => fake()->optional()->numberBetween(100, 1000),
            'height' => fake()->optional()->numberBetween(100, 1000),
        ];
    }
}
