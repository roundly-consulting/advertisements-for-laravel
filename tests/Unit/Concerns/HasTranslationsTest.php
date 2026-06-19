<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Models\Placement;

afterEach(function (): void {
    app()->setLocale('en');
});

it('gets, sets, forgets and checks a translation', function (): void {
    $placement = Placement::factory()->create(['name' => ['en' => 'Sidebar']]);

    $placement->setTranslation('name', 'de', 'Seitenleiste')->save();

    expect($placement->getTranslation('name', 'de'))->toBe('Seitenleiste')
        ->and($placement->getTranslation('name', 'en'))->toBe('Sidebar')
        ->and($placement->hasTranslation('name', 'de'))->toBeTrue()
        ->and($placement->hasTranslation('name', 'fr'))->toBeFalse();

    $placement->forgetTranslation('name', 'de')->save();

    expect($placement->fresh()->hasTranslation('name', 'de'))->toBeFalse();
});

it('returns the full translation map', function (): void {
    $placement = Placement::factory()->create(['name' => ['en' => 'Sidebar', 'de' => 'Seitenleiste']]);

    expect($placement->getTranslations('name'))->toBe(['en' => 'Sidebar', 'de' => 'Seitenleiste']);
});

it('resolves the active locale value', function (): void {
    $placement = Placement::factory()->create(['name' => ['en' => 'Sidebar', 'de' => 'Seitenleiste']]);

    app()->setLocale('de');

    expect($placement->name)->toBe('Seitenleiste');
});

it('falls back to the fallback locale when the active locale is missing', function (): void {
    config()->set('advertisements.fallback_locale', 'en');
    $placement = Placement::factory()->create(['name' => ['en' => 'Sidebar']]);

    app()->setLocale('de');

    expect($placement->name)->toBe('Sidebar');
});

it('falls back to the first stored value when neither locale matches', function (): void {
    config()->set('advertisements.fallback_locale', 'en');
    $placement = Placement::factory()->create(['name' => ['fr' => 'Barre latérale']]);

    app()->setLocale('de');

    expect($placement->name)->toBe('Barre latérale');
});

it('returns null when there is no translation at all', function (): void {
    $placement = Placement::factory()->make(['name' => []]);

    expect($placement->getTranslation('name'))->toBeNull();
});

it('stores a bare string under the current locale', function (): void {
    app()->setLocale('de');
    $placement = new Placement;

    $placement->name = 'Kopfzeile';

    expect($placement->getTranslations('name'))->toBe(['de' => 'Kopfzeile']);
});

it('replaces the whole map with setTranslations', function (): void {
    $placement = Placement::factory()->create(['name' => ['en' => 'Old']]);

    $placement->setTranslations('name', ['en' => 'New', 'de' => 'Neu'])->save();

    expect($placement->fresh()->getTranslations('name'))->toBe(['en' => 'New', 'de' => 'Neu']);
});

it('decodes a raw json string from a freshly loaded model', function (): void {
    $created = Placement::factory()->create(['name' => ['en' => 'Sidebar']]);

    // A model loaded from the database holds the raw JSON string before casting.
    $loaded = Placement::query()->findOrFail($created->id);

    expect($loaded->getTranslations('name'))->toBe(['en' => 'Sidebar']);
});
