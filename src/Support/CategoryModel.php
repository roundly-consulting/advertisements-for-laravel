<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Support;

use Illuminate\Database\Eloquent\Builder;
use RoundlyConsulting\Advertisements\Models\Category;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing a category from `advertisements.category_model`.
 *
 * The toolkit ModelResolver validates that the configured value is a real Eloquent
 * model; it cannot know it is *ours*, so anything that is not a Category (and so
 * cannot answer the package's tree reads) falls back to the packaged model.
 */
final class CategoryModel
{
    /** @return class-string<Category> */
    public static function class(): string
    {
        $model = ModelResolver::for('advertisements.category_model', Category::class);

        return is_a($model, Category::class, true) ? $model : Category::class;
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
