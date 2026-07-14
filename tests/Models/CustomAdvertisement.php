<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Tests\Models;

use RoundlyConsulting\Advertisements\Models\Advertisement;

/**
 * A host's own advertisement model, exactly as `advertisements.model` invites.
 * Its class name deliberately differs from the packaged one so any relation that
 * derives a key from the class name breaks loudly.
 */
final class CustomAdvertisement extends Advertisement
{
    protected $table = 'advertisements';
}
