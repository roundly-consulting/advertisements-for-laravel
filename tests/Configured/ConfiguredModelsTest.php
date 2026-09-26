<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Enums\AdvertisementEventType;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\Advertisements\Tests\Models\CustomAdvertisement;
use RoundlyConsulting\Advertisements\Tests\Models\CustomAdvertisementEvent;
use RoundlyConsulting\Advertisements\Tests\Models\CustomCategory;
use RoundlyConsulting\Advertisements\Tests\Models\CustomPlacement;
use RoundlyConsulting\MediaLibrary\Models\Media;

/**
 * The four `advertisements.*model` keys document pointing the package at a host's
 * own subclass. This drives the whole flow — create, categorise, attach a
 * placement, record an impression, read the reports — through all four swapped
 * models at once.
 *
 * It is the pin for the FK bug the retrofit found: the relations named no foreign
 * key and no pivot table, so Eloquent derived them from the CLASS name — a host
 * subclass silently queried `custom_advertisement_id` and an
 * `advertisement_custom_placement` pivot that no migration ever created.
 */
beforeEach(function (): void {
    // The four model keys are NOT set here: they land before the providers boot, via
    // SwappedModelsTestCase. A `beforeEach` runs AFTER boot, so setting them here read back
    // correctly while leaving every observer and listener on the packaged classes — which is
    // why this file could not have caught a boot-time bug it was named for.
    $this->category = CustomCategory::query()->create([
        'name' => ['en' => 'Homepage'],
        'slug' => 'homepage',
    ]);

    $this->placement = CustomPlacement::query()->create([
        'name' => ['en' => 'Sidebar'],
        'slug' => 'sidebar',
        'width' => 300,
        'height' => 250,
    ]);
});

it('creates, categorises and reads back the configured advertisement model', function (): void {
    $ad = Advertisements::create(AdvertisementData::fromMinor('Boots', 4900, category: 'homepage'));

    expect($ad)->toBeInstanceOf(CustomAdvertisement::class)
        ->and($ad->category_id)->toBe($this->category->getKey())
        ->and($ad->category)->toBeInstanceOf(CustomCategory::class)
        ->and(Advertisements::query()->find($ad->getKey()))->toBeInstanceOf(CustomAdvertisement::class);
});

it('attaches placements through the pivot the migration actually created', function (): void {
    $ad = Advertisements::create(AdvertisementData::fromMinor('Boots', 4900));

    Advertisements::attachPlacements($ad, ['sidebar']);

    expect($ad->placements()->getTable())->toBe('advertisement_placement')
        ->and($ad->placements()->getForeignPivotKeyName())->toBe('advertisement_id')
        ->and($ad->placements()->getRelatedPivotKeyName())->toBe('placement_id')
        ->and($ad->placements()->count())->toBe(1)
        ->and($ad->placements()->first())->toBeInstanceOf(CustomPlacement::class);

    // …and the inverse side reads the same pivot.
    expect($this->placement->advertisements()->count())->toBe(1)
        ->and($this->placement->advertisements()->first())->toBeInstanceOf(CustomAdvertisement::class);
});

it('records tracking events onto the packaged foreign key', function (): void {
    $ad = Advertisements::create(AdvertisementData::fromMinor('Boots', 4900));

    $event = Advertisements::recordImpression($ad, 'sidebar');

    expect($event)->toBeInstanceOf(CustomAdvertisementEvent::class)
        ->and($ad->events()->getForeignKeyName())->toBe('advertisement_id')
        ->and($ad->events()->count())->toBe(1)
        ->and($ad->events()->first())->toBeInstanceOf(CustomAdvertisementEvent::class);

    expect($event->advertisement)->toBeInstanceOf(CustomAdvertisement::class)
        ->and($event->placement)->toBeInstanceOf(CustomPlacement::class)
        ->and($event->type)->toBe(AdvertisementEventType::Impression);
});

it('reads a category tree and its advertisements through the configured model', function (): void {
    $child = CustomCategory::query()->create([
        'name' => ['en' => 'Homepage hero'],
        'slug' => 'homepage-hero',
        'parent_id' => $this->category->getKey(),
    ]);

    $ad = Advertisements::create(AdvertisementData::fromMinor('Boots', 4900, category: 'homepage-hero'));
    $ad->publish();

    expect($this->category->advertisements()->getForeignKeyName())->toBe('category_id')
        ->and($child->advertisements()->count())->toBe(1)
        ->and($child->advertisements()->first())->toBeInstanceOf(CustomAdvertisement::class)
        ->and($child->parent)->toBeInstanceOf(CustomCategory::class)
        ->and($this->category->children()->first())->toBeInstanceOf(CustomCategory::class)
        ->and($this->category->descendants())->toHaveCount(1);

    // The descendant-aware scope resolves through the configured model too.
    expect(Advertisements::query()->inCategory('homepage', includeDescendants: true)->count())->toBe(1);
});

it('reports per-country counts for the configured models', function (): void {
    $ad = Advertisements::create(AdvertisementData::fromMinor('Boots', 4900));

    Advertisements::recordImpression($ad, 'sidebar');
    Advertisements::recordClick($ad, 'sidebar');

    expect($ad->impressions())->toBe(1)
        ->and($ad->clicks())->toBe(1)
        ->and($ad->impressionsByCountry())->toBe([])
        ->and($ad->clicksByCountry())->toBe([]);
});

it('falls back to the packaged models when the config names a foreign model', function (): void {
    // A real Eloquent model that is not one of ours: the toolkit resolver validates
    // "is a Model", the package must still validate "is one of MINE".
    config()->set('advertisements.model', Media::class);
    config()->set('advertisements.event_model', Media::class);

    $ad = Advertisements::create(AdvertisementData::fromMinor('Boots', 4900));

    expect($ad)->toBeInstanceOf(Advertisement::class)
        ->and($ad)->not->toBeInstanceOf(CustomAdvertisement::class)
        ->and(Advertisements::recordImpression($ad))->toBeInstanceOf(AdvertisementEvent::class);
});

it('generates and resolves slugs through the configured models', function (): void {
    $placement = CustomPlacement::query()->create(['name' => ['en' => 'Footer Strip']]);
    $category = CustomCategory::query()->create(['name' => ['en' => 'Garden Tools']]);

    $ad = Advertisements::create(AdvertisementData::fromMinor('Boots', 4900, category: 'garden-tools'));
    Advertisements::attachPlacements($ad, ['footer-strip']);

    expect($placement->slug)->toBe('footer-strip')
        ->and($category->slug)->toBe('garden-tools')
        ->and($ad)->toBeInstanceOf(CustomAdvertisement::class)
        ->and($ad->getTranslation('slug', 'en'))->toBe('boots')
        ->and($ad->category_id)->toBe($category->getKey())
        ->and($ad->placements()->first()?->is($placement))->toBeTrue()
        ->and(Advertisements::query()->whereSlug('boots')->first())->toBeInstanceOf(CustomAdvertisement::class)
        ->and((new CustomAdvertisement)->getRouteKeyName())->toBe('slug');
});
