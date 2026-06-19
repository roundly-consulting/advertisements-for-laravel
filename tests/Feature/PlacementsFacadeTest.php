<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;

it('returns only active ads in a placement via for()', function (): void {
    $sidebar = Placement::factory()->create(['slug' => 'sidebar']);

    $active = Advertisement::factory()->published()->create();
    $draft = Advertisement::factory()->create();
    $expired = Advertisement::factory()->expired()->create();
    $archived = Advertisement::factory()->archived()->create();

    foreach ([$active, $draft, $expired, $archived] as $ad) {
        $ad->placements()->attach($sidebar);
    }

    $results = Advertisements::for('sidebar')->get();

    expect($results->pluck('id')->all())->toBe([$active->id]);
});

it('attaches, detaches and syncs placements through the facade', function (): void {
    $ad = Advertisement::factory()->create();
    Placement::factory()->create(['slug' => 'sidebar']);
    Placement::factory()->create(['slug' => 'header']);

    Advertisements::attachPlacements($ad, ['sidebar', 'header']);
    expect($ad->placements()->count())->toBe(2);

    Advertisements::detachPlacements($ad, ['sidebar']);
    expect($ad->placements()->pluck('slug')->all())->toBe(['header']);

    Advertisements::syncPlacements($ad, ['sidebar']);
    expect($ad->placements()->pluck('slug')->all())->toBe(['sidebar']);
});
