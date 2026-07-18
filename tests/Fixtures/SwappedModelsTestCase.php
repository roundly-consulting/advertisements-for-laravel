<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Tests\Fixtures;

use RoundlyConsulting\Advertisements\Tests\Models\CustomAdvertisement;
use RoundlyConsulting\Advertisements\Tests\Models\CustomAdvertisementEvent;
use RoundlyConsulting\Advertisements\Tests\Models\CustomCategory;
use RoundlyConsulting\Advertisements\Tests\Models\CustomPlacement;
use RoundlyConsulting\Advertisements\Tests\TestCase;

/**
 * The base case for `tests/Configured` — the suite booted as a host that has swapped all
 * FOUR advertisements model seams in its own `config/advertisements.php`.
 *
 * Boot order is the whole point, and it is why this is a separate base case (and therefore a
 * separate directory — Pest binds a test case per DIRECTORY, not per file). The superseded
 * `Feature/ConfiguredModelsTest` set the four keys in a `beforeEach`, which runs AFTER the
 * providers boot: it reads back correctly while leaving every observer and listener on the
 * packaged classes, so it is structurally incapable of catching a boot-time bug. A real host
 * sets these keys before boot; so does this.
 *
 * Note the `array_merge(parent::configBeforeBoot(), …)`: dropping it would silently discard
 * the base's own media-library wiring, with no error and no red — the same decapitation an
 * un-parented `defineEnvironment()` override causes one level up.
 *
 * @see TestCase
 */
abstract class SwappedModelsTestCase extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'advertisements.model' => CustomAdvertisement::class,
            'advertisements.placement_model' => CustomPlacement::class,
            'advertisements.category_model' => CustomCategory::class,
            'advertisements.event_model' => CustomAdvertisementEvent::class,
        ]);
    }
}
