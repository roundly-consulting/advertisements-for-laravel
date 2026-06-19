<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Advertisements\Support\PlacementResolver;

it('resolves a model, id and slug to a key', function (): void {
    $placement = Placement::factory()->create(['slug' => 'sidebar']);
    $resolver = app(PlacementResolver::class);

    expect($resolver->resolveKey($placement))->toBe($placement->id)
        ->and($resolver->resolveKey($placement->id))->toBe($placement->id)
        ->and($resolver->resolveKey('sidebar'))->toBe($placement->id);
});

it('resolves null to null and an unknown slug to null', function (): void {
    $resolver = app(PlacementResolver::class);

    expect($resolver->resolveKey(null))->toBeNull()
        ->and($resolver->resolveKey('missing'))->toBeNull();
});

it('skips unresolvable references when resolving a list', function (): void {
    $placement = Placement::factory()->create(['slug' => 'sidebar']);
    $resolver = app(PlacementResolver::class);

    expect($resolver->resolveKeys(['sidebar', 'missing']))->toBe([$placement->id]);
});
