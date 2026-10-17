<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use RoundlyConsulting\Advertisements\Models\Category;
use RoundlyConsulting\Advertisements\Support\CategoryModel;

/** @extends Factory<Category> */
final class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Build the configured model, not the packaged one: a host that swaps
     * `advertisements.category_model` gets its own class, with its casts,
     * events and observers.
     *
     * @return class-string<Category>
     */
    public function modelName(): string
    {
        return CategoryModel::class();
    }

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
