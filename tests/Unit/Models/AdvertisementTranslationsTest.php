<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Models\Advertisement;

afterEach(function (): void {
    app()->setLocale('en');
});

it('resolves name and description for the active locale', function (): void {
    $ad = Advertisement::factory()->create(['name' => 'Vintage Bike']);
    $ad->setTranslation('name', 'de', 'Vintage Fahrrad')
        ->setTranslation('description', 'de', 'Schönes Rad')
        ->save();

    expect($ad->getTranslation('name', 'en'))->toBe('Vintage Bike');

    app()->setLocale('de');

    expect($ad->fresh()->name)->toBe('Vintage Fahrrad')
        ->and($ad->fresh()->description)->toBe('Schönes Rad');
});

it('keeps single-locale string assignment working', function (): void {
    $ad = Advertisement::factory()->create();

    $ad->name = 'Plain String';
    $ad->save();

    expect($ad->fresh()->getTranslations('name'))->toBe(['en' => 'Plain String'])
        ->and($ad->fresh()->name)->toBe('Plain String');
});

it('returns the full translation map', function (): void {
    $ad = Advertisement::factory()->create(['name' => 'Bike']);
    $ad->setTranslation('name', 'de', 'Fahrrad')->save();

    expect($ad->fresh()->getTranslations('name'))->toBe([
        'en' => 'Bike',
        'de' => 'Fahrrad',
    ]);
});
