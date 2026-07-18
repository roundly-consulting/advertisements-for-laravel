<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Tests\Models;

use RoundlyConsulting\Advertisements\Models\Category;
use RoundlyConsulting\Testing\Fixtures\Concerns\CountsCreations;

/** A host's own category model (`advertisements.category_model`). */
final class CustomCategory extends Category
{
    use CountsCreations;

    protected $table = 'categories';
}
