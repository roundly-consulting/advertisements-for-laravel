<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Advertisements\Models\Category;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing a category from `advertisements.category_model`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class CategoryModel
{
    /** @return class-string<Category> */
    public static function class(): string
    {
        return ModelResolver::for('advertisements.category_model', Category::class);
    }

    public static function new(): Category
    {
        $model = self::class();

        return new $model;
    }

    /** @return Builder<Category> */
    public static function query(): Builder
    {
        return self::class()::query();
    }
}
