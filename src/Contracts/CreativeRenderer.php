<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Contracts;

use Illuminate\Support\HtmlString;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;

/**
 * Resolves what to render for an advertisement in a placement (image creative or text-ad
 * fallback). Bind your own implementation to swap the rendering chain.
 */
interface CreativeRenderer
{
    /**
     * @param  array<string, string>  $attributes
     */
    public function render(Advertisement $advertisement, Placement|int|string $placement, array $attributes = []): HtmlString;
}
