<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Advertisements\Support\PlacementModel;

/** @extends Factory<Placement> */
final class PlacementFactory extends Factory
{
    protected $model = Placement::class;

    /**
     * Build the configured model, not the packaged one: a host that swaps
     * `advertisements.placement_model` gets its own class, with its casts,
     * events and observers.
     *
     * @return class-string<Placement>
     */
    public function modelName(): string
    {
        return PlacementModel::class();
    }

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
