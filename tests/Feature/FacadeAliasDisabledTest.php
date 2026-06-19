<?php

declare(strict_types=1);

namespace RoundlyConsulting\Advertisements\Tests\Feature;

use Illuminate\Foundation\AliasLoader;
use RoundlyConsulting\Advertisements\Tests\TestCase;

/**
 * Dedicated case so the alias opt-out is decided before the package boots.
 */
final class FacadeAliasDisabledTest extends TestCase
{
    protected function setUp(): void
    {
        // The alias loader is a process-wide singleton; clear any alias left by
        // other tests so this opt-out assertion is order-independent.
        AliasLoader::getInstance()->setAliases([]);

        parent::setUp();
    }

    /** @return array<string, mixed> */
    protected function packageConfig(): array
    {
        return ['advertisements.register_facade_alias' => false];
    }

    public function test_it_does_not_register_the_alias_when_opted_out(): void
    {
        $this->assertArrayNotHasKey('Advertisements', AliasLoader::getInstance()->getAliases());
    }
}
