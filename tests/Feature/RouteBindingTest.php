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

it('binds a stale-locale slug through the any-locale chain', function (): void {
    config()->set('advertisements.fallback_locale', 'en');
    $ad = Advertisement::factory()->create(['name' => ['sk' => 'Iba po slovensky']]);

    // Current locale de, fallback en: neither holds a slug, yet the sk URL still resolves.
    app()->setLocale('de');

    $this->get('/ads/iba-po-slovensky')
        ->assertOk()
        ->assertSee((string) $ad->id);
});

it('prefers the current-locale match when two ads share a slug across locales', function (): void {
    $english = Advertisement::factory()->create(['name' => ['en' => 'Gift']]);
    $german = Advertisement::factory()->create(['name' => ['de' => 'Gift']]);

    app()->setLocale('de');

    $this->get('/ads/gift')->assertOk()->assertSee((string) $german->id);

    app()->setLocale('en');

    $this->get('/ads/gift')->assertOk()->assertSee((string) $english->id);
});

it('generates route urls from the current-locale slug', function (): void {
    $ad = Advertisement::factory()->create(['name' => ['en' => 'Blue Kettle', 'de' => 'Blauer Kessel']]);

    expect($ad->getRouteKey())->toBe('blue-kettle');

    app()->setLocale('de');

    expect($ad->getRouteKey())->toBe('blauer-kessel');
});

it('redirects a retired slug to the current one when slug history is on', function (): void {
    config()->set('advertisements.slugs.history', true);

    $ad = Advertisement::factory()->create(['name' => 'Old Offer']);
    $ad->update(['name' => 'New Offer']);

    $this->get('/ads/old-offer?ref=mail')
        ->assertStatus(301)
        ->assertRedirect('/ads/new-offer?ref=mail');

    $this->get('/ads/new-offer')->assertOk()->assertSee((string) $ad->id);
});

it('does not redirect a retired slug while slug history is off', function (): void {
    $ad = Advertisement::factory()->create(['name' => 'Old Offer']);
    $ad->update(['name' => 'New Offer']);

    $this->get('/ads/old-offer')->assertNotFound();
});
