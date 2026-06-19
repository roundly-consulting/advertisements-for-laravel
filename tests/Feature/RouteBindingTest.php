<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use RoundlyConsulting\Advertisements\Models\Advertisement;

beforeEach(function (): void {
    Route::middleware('web')->get('/ads/{advertisement}', function (Advertisement $advertisement): string {
        return (string) $advertisement->id;
    });
});

afterEach(function (): void {
    app()->setLocale('en');
});

it('uses slug as the route key', function (): void {
    expect((new Advertisement)->getRouteKeyName())->toBe('slug');
});

it('binds by the current-locale slug', function (): void {
    $ad = Advertisement::factory()->create(['name' => 'Find Me']);

    $this->get('/ads/find-me')
        ->assertOk()
        ->assertSee((string) $ad->id);
});

it('binds by the fallback-locale slug when the current locale is missing', function (): void {
    config()->set('advertisements.fallback_locale', 'en');
    $ad = Advertisement::factory()->create(['name' => 'Only English']);

    app()->setLocale('de');

    $this->get('/ads/only-english')
        ->assertOk()
        ->assertSee((string) $ad->id);
});

it('returns 404 for an unknown slug', function (): void {
    Advertisement::factory()->create(['name' => 'Something']);

    $this->get('/ads/nope')->assertNotFound();
});

it('defers to the parent resolver for non-slug fields', function (): void {
    $ad = Advertisement::factory()->create();

    Route::middleware('web')->get('/ads-by-id/{advertisement:id}', function (Advertisement $advertisement): string {
        return (string) $advertisement->id;
    });

    $this->get('/ads-by-id/'.$ad->id)
        ->assertOk()
        ->assertSee((string) $ad->id);
});

it('does not fall back when the current locale equals the fallback', function (): void {
    config()->set('advertisements.fallback_locale', 'en');
    Advertisement::factory()->create(['name' => 'English Only']);

    // Current locale en == fallback en: only the en slug branch runs.
    $this->get('/ads/english-only')->assertOk();
});
