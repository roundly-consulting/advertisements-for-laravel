<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Actions\AttachPlacements;
use RoundlyConsulting\Advertisements\Actions\DetachPlacements;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;

it('detaches placements by slug', function (): void {
    $ad = Advertisement::factory()->create();
    $sidebar = Placement::factory()->create(['slug' => 'sidebar']);
    $header = Placement::factory()->create(['slug' => 'header']);

    app(AttachPlacements::class)->execute($ad, [$sidebar, $header]);

    $result = app(DetachPlacements::class)->execute($ad, ['sidebar']);

    expect($result->placements->pluck('id')->all())->toBe([$header->id]);
});
