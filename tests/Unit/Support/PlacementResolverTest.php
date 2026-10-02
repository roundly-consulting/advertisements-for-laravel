<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Advertisement;
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

it('resolves a numeric string as an id, as form and route input arrives', function (): void {
    $placement = Placement::factory()->create(['slug' => 'sidebar']);
    $resolver = app(PlacementResolver::class);

    expect($resolver->resolveKey((string) $placement->id))->toBe($placement->id)
        ->and($resolver->resolveKeys([(string) $placement->id]))->toBe([$placement->id])
        ->and($resolver->resolveKey('999999'))->toBeNull();
});

it('prefers a placement whose slug is the numeric string over the id', function (): void {
    $byId = Placement::factory()->create(['slug' => 'sidebar']);
    $bySlug = Placement::factory()->create(['slug' => (string) $byId->id]);

    expect(app(PlacementResolver::class)->resolveKey((string) $byId->id))->toBe($bySlug->id);
});

it('attaches and targets placements given as numeric strings', function (): void {
    $placement = Placement::factory()->create(['slug' => 'sidebar']);
    $ad = Advertisement::factory()->published()->create();

    Advertisements::for($ad)->placements()->attach([(string) $placement->id]);

    expect($ad->placements()->pluck('placements.id')->all())->toBe([$placement->id])
        ->and(Advertisements::in((string) $placement->id)->pluck('id')->all())->toBe([$ad->id])
        ->and(Advertisements::for($ad)->runsIn((string) $placement->id))->toBeTrue();
});

it('names the creative bucket of a placement given as a numeric string by its slug', function (): void {
    $placement = Placement::factory()->create(['slug' => 'sidebar']);

    expect((new Advertisement)->creativeBucketName((string) $placement->id))->toBe('creative:sidebar');
});
