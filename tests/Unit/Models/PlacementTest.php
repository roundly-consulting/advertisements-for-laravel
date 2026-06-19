<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;

it('creates a placement with a slug and a translatable name', function (): void {
    $placement = Placement::factory()->create([
        'slug' => 'sidebar',
        'name' => ['en' => 'Sidebar'],
    ]);

    expect($placement->slug)->toBe('sidebar')
        ->and($placement->name)->toBe('Sidebar')
        ->and($placement->getTranslations('name'))->toBe(['en' => 'Sidebar']);
});

it('round-trips a multi-locale name', function (): void {
    $placement = Placement::factory()->create(['name' => ['en' => 'Header']]);

    $placement->setTranslation('name', 'de', 'Kopfzeile')->save();

    app()->setLocale('de');

    expect($placement->fresh()->name)->toBe('Kopfzeile');
});

it('exposes advertisements with pivot meta', function (): void {
    $placement = Placement::factory()->create();
    $ad = Advertisement::factory()->create();

    $placement->advertisements()->attach($ad, ['meta' => json_encode(['weight' => 5])]);

    $loaded = $placement->advertisements()->first();

    expect($loaded)->not->toBeNull()
        ->and($loaded->id)->toBe($ad->id)
        ->and($loaded->pivot->meta)->toBe(json_encode(['weight' => 5]));
});

it('uses slug as its route key', function (): void {
    expect((new Placement)->getRouteKeyName())->toBe('slug');
});
