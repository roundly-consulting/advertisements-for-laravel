<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Actions\AttachPlacements;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;

it('attaches placements by id and slug', function (): void {
    $ad = Advertisement::factory()->create();
    $sidebar = Placement::factory()->create(['slug' => 'sidebar']);
    $header = Placement::factory()->create(['slug' => 'header']);

    $result = app(AttachPlacements::class)->execute($ad, [$sidebar->id, 'header']);

    expect($result->placements->pluck('id')->sort()->values()->all())
        ->toBe([$sidebar->id, $header->id]);
});

it('ignores duplicate attaches', function (): void {
    $ad = Advertisement::factory()->create();
    $placement = Placement::factory()->create();

    app(AttachPlacements::class)->execute($ad, [$placement->id]);
    $result = app(AttachPlacements::class)->execute($ad, [$placement->id]);

    expect($result->placements)->toHaveCount(1);
});
