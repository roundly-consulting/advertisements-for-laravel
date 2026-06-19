<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Support;

use RoundlyConsulting\Advertisements\Models\Category;

/**
 * Normalises a category reference (model, id, or slug) to its primary key. An
 * unknown slug resolves to null — categories are never created implicitly.
 */
final class CategoryResolver
{
    public function resolveKey(Category|int|string|null $category): ?int
    {
        if ($category === null) {
            return null;
        }

        if ($category instanceof Category) {
            return $category->getKey();
        }

        if (is_int($category)) {
            return $category;
        }

        return $this->modelClass()::query()
            ->where('slug', $category)
            ->value('id');
    }

    /**
     * @return class-string<Category>
     */
    private function modelClass(): string
    {
        /** @var class-string<Category> $model */
        $model = config('advertisements.category_model', Category::class);

        return $model;
    }
}
