<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use RoundlyConsulting\Advertisements\Models\Category;

/** @extends Factory<Category> */
final class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 100000),
            'name' => ['en' => Str::title($name)],
        ];
    }
}
