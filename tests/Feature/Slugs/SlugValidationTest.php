<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Sluggable\Rules\UniqueSlug;
use RoundlyConsulting\Sluggable\Rules\ValidSlug;

/**
 * Hosts validate admin forms with sluggable's rules against the package models; the rules use
 * exactly the generation semantics (trashed rows count, per-locale for advertisements).
 */
it('validates a placement slug for uniqueness and format', function (): void {
    $sidebar = Placement::factory()->create(['slug' => 'sidebar']);

    $taken = Validator::make(['slug' => 'sidebar'], ['slug' => [UniqueSlug::for(Placement::class)]]);
    $own = Validator::make(['slug' => 'sidebar'], ['slug' => [UniqueSlug::for(Placement::class)->ignore($sidebar)]]);
    $malformed = Validator::make(['slug' => 'Side Bar'], ['slug' => [ValidSlug::for(Placement::class)]]);

    expect($taken->fails())->toBeTrue()
        ->and($own->passes())->toBeTrue()
        ->and($malformed->fails())->toBeTrue();
});

it('validates an advertisement slug per locale', function (): void {
    Advertisement::factory()->create(['name' => ['en' => 'Bike', 'de' => 'Fahrrad']]);

    $validator = Validator::make(
        ['slug' => ['en' => 'bike', 'de' => 'neu']],
        ['slug' => [UniqueSlug::for(Advertisement::class)]],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->keys())->toBe(['slug.en']);
});
