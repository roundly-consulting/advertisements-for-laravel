<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Support;

use RoundlyConsulting\Advertisements\Models\Category;

/**
 * Normalises a category reference (model, id, or slug) to its primary key. An
 * unknown slug resolves to null — categories are never created implicitly.
 *
 * A string is a slug first; a digit-only string no category uses as its slug is then
 * taken as an id (form and route input arrives as strings), like sluggable's key fallback.
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

        $key = CategoryModel::query()->whereSlug($category)->value('id')
            ?? (KeyString::isKey($category) ? CategoryModel::query()->whereKey((int) $category)->value('id') : null);

        return $key === null ? null : (int) $key;
    }
}
