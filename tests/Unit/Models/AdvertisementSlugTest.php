<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Models\Advertisement;

afterEach(function (): void {
    app()->setLocale('en');
});

it('generates a slug per locale from that locale name', function (): void {
    $ad = Advertisement::factory()->create(['name' => 'Vintage Bike']);

    expect($ad->getTranslations('slug'))->toBe(['en' => 'vintage-bike']);

    app()->setLocale('de');
    $ad->setTranslation('name', 'de', 'Vintage Fahrrad')->save();

    // `toEqual`: what matters is that each locale slugged its own name. `jsonb` sorts object
    // keys, so the map reads back `de` first on Postgres — storage order, not the contract.
    expect($ad->fresh()?->getTranslations('slug'))->toEqual([
        'en' => 'vintage-bike',
        'de' => 'vintage-fahrrad',
    ]);
});

it('suffixes a same-locale slug collision', function (): void {
    $first = Advertisement::factory()->create(['name' => 'Same Name']);
    $second = Advertisement::factory()->create(['name' => 'Same Name']);

    expect($first->getTranslation('slug', 'en'))->toBe('same-name')
        ->and($second->getTranslation('slug', 'en'))->toBe('same-name-1');
});

it('allows the same slug across different locales', function (): void {
    $en = Advertisement::factory()->create(['name' => 'Bike']);

    app()->setLocale('de');
    $de = Advertisement::factory()->create(['name' => 'Bike']);

    expect($en->getTranslation('slug', 'en'))->toBe('bike')
        ->and($de->getTranslation('slug', 'de'))->toBe('bike');
});

it('does not regenerate an existing locale slug when an unrelated locale changes', function (): void {
    $ad = Advertisement::factory()->create(['name' => 'Original']);
    $originalSlug = $ad->getTranslation('slug', 'en');

    app()->setLocale('de');
    $ad->setTranslation('name', 'de', 'Etwas')->save();

    expect($ad->fresh()->getTranslation('slug', 'en'))->toBe($originalSlug);
});

it('does not generate a slug when there is no name to slug from', function (): void {
    $ad = new Advertisement;
    $ad->setTranslations('name', [])->setTranslations('slug', [])->save();

    expect($ad->fresh()->getTranslations('slug'))->toBe([]);
});

it('matches a record by its current-locale slug via json path', function (): void {
    $ad = Advertisement::factory()->create(['name' => 'Find Me']);

    $found = Advertisement::query()->where('slug->en', 'find-me')->first();

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($ad->id);
});
