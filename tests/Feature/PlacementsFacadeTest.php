<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\AdvertisementHandle;
use RoundlyConsulting\Advertisements\AdvertisementPlacements;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;

it('returns only active ads in a placement via in()', function (): void {
    $sidebar = Placement::factory()->create(['slug' => 'sidebar']);

    $active = Advertisement::factory()->published()->create();
    $draft = Advertisement::factory()->create();
    $expired = Advertisement::factory()->expired()->create();
    $archived = Advertisement::factory()->archived()->create();

    foreach ([$active, $draft, $expired, $archived] as $ad) {
        $ad->placements()->attach($sidebar);
    }

    expect(Advertisements::in('sidebar')->pluck('id')->all())->toBe([$active->id])
        ->and(Advertisements::in($sidebar)->pluck('id')->all())->toBe([$active->id])
        ->and(Advertisements::in($sidebar->id)->pluck('id')->all())->toBe([$active->id]);
});

it('scopes an ad through for() and hands out its placements accessor', function (): void {
    $ad = Advertisement::factory()->create();

    expect(Advertisements::for($ad))->toBeInstanceOf(AdvertisementHandle::class)
        ->and(Advertisements::for($ad)->placements())->toBeInstanceOf(AdvertisementPlacements::class);
});

it('attaches, detaches and syncs placements through the facade', function (): void {
    $ad = Advertisement::factory()->create();
    $sidebar = Placement::factory()->create(['slug' => 'sidebar']);
    Placement::factory()->create(['slug' => 'header']);

    $returned = Advertisements::for($ad)->placements()->attach(['sidebar', 'header']);
    expect($returned)->toBe($ad)
        ->and($ad->placements()->count())->toBe(2)
        ->and($returned->relationLoaded('placements'))->toBeTrue();

    Advertisements::for($ad)->placements()->detach([$sidebar]);
    expect($ad->placements()->pluck('slug')->all())->toBe(['header']);

    Advertisements::for($ad)->placements()->sync([$sidebar->id]);
    expect($ad->placements()->pluck('slug')->all())->toBe(['sidebar']);
});

it('skips unknown placement slugs when attaching', function (): void {
    $ad = Advertisement::factory()->create();
    Placement::factory()->create(['slug' => 'sidebar']);

    Advertisements::for($ad)->placements()->attach(['sidebar', 'nowhere']);

    expect($ad->placements()->pluck('slug')->all())->toBe(['sidebar']);
});

it('tells whether an ad runs in a placement', function (): void {
    $ad = Advertisement::factory()->create();
    $sidebar = Placement::factory()->create(['slug' => 'sidebar']);
    $header = Placement::factory()->create(['slug' => 'header']);
    $ad->placements()->attach($sidebar);

    expect(Advertisements::for($ad)->runsIn('sidebar'))->toBeTrue()
        ->and(Advertisements::for($ad)->runsIn($sidebar))->toBeTrue()
        ->and(Advertisements::for($ad)->runsIn($sidebar->id))->toBeTrue()
        ->and(Advertisements::for($ad)->runsIn($header))->toBeFalse()
        ->and(Advertisements::for($ad)->runsIn('nowhere'))->toBeFalse();
});
