<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;

it('attaches an advertisement to multiple placements', function (): void {
    $ad = Advertisement::factory()->create();
    $sidebar = Placement::factory()->create(['slug' => 'sidebar']);
    $header = Placement::factory()->create(['slug' => 'header']);

    $ad->placements()->attach([$sidebar->id, $header->id]);

    expect($ad->placements()->count())->toBe(2);
});

it('loads placements with pivot meta', function (): void {
    $ad = Advertisement::factory()->create();
    $placement = Placement::factory()->create();

    $ad->placements()->attach($placement, ['meta' => json_encode(['weight' => 10])]);

    expect($ad->placements()->first()->pivot->meta)->toBe(json_encode(['weight' => 10]));
});

it('scopes by placement model, id and slug', function (string $by): void {
    $ad = Advertisement::factory()->create();
    $placement = Placement::factory()->create(['slug' => 'sidebar']);
    $ad->placements()->attach($placement);

    Advertisement::factory()->create();

    $reference = match ($by) {
        'model' => $placement,
        'id' => $placement->id,
        'slug' => 'sidebar',
    };

    $results = Advertisement::query()->forPlacement($reference)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($ad->id);
})->with(['model', 'id', 'slug']);

it('does not duplicate a pivot row on repeated attach', function (): void {
    $ad = Advertisement::factory()->create();
    $placement = Placement::factory()->create();

    $ad->placements()->syncWithoutDetaching([$placement->id]);
    $ad->placements()->syncWithoutDetaching([$placement->id]);

    expect($ad->placements()->count())->toBe(1);
});
