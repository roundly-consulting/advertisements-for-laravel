<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Tests\Models;

use RoundlyConsulting\Advertisements\Models\Category;

/** A host's own category model (`advertisements.category_model`). */
final class CustomCategory extends Category
{
    protected $table = 'categories';
}
