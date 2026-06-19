<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Actions\AttachPlacements;
use RoundlyConsulting\Advertisements\Actions\SyncPlacements;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;

it('replaces the placement set', function (): void {
    $ad = Advertisement::factory()->create();
    $sidebar = Placement::factory()->create(['slug' => 'sidebar']);
    $header = Placement::factory()->create(['slug' => 'header']);
    $footer = Placement::factory()->create(['slug' => 'footer']);

    app(AttachPlacements::class)->execute($ad, [$sidebar, $header]);

    $result = app(SyncPlacements::class)->execute($ad, ['footer']);

    expect($result->placements->pluck('id')->all())->toBe([$footer->id]);
});
