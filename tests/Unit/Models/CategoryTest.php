<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Category;
use RoundlyConsulting\Sluggable\Exceptions\SlugAlreadyTakenException;

afterEach(function (): void {
    app()->setLocale('en');
});

it('creates a category with a translatable name', function (): void {
    $category = Category::factory()->create(['name' => ['en' => 'Vehicles']]);

    expect($category->name)->toBe('Vehicles')
        ->and($category->getTranslations('name'))->toBe(['en' => 'Vehicles']);
});

it('nests categories through parent and children', function (): void {
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    expect($child->parent->is($parent))->toBeTrue()
        ->and($parent->children->pluck('id')->all())->toBe([$child->id]);
});

it('returns the full descendant subtree', function (): void {
    $root = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $root->id]);
    $grandchild = Category::factory()->create(['parent_id' => $child->id]);

    expect($root->descendants()->pluck('id')->sort()->values()->all())
        ->toBe([$child->id, $grandchild->id]);
});

it('relates advertisements to a category', function (): void {
    $category = Category::factory()->create();
    $ad = Advertisement::factory()->create(['category_id' => $category->id]);

    expect($category->advertisements()->count())->toBe(1)
        ->and($ad->category->is($category))->toBeTrue();
});

it('uses slug as its route key', function (): void {
    expect((new Category)->getRouteKeyName())->toBe('slug');
});

it('generates the slug from the fallback-locale name when none is given', function (): void {
    config()->set('advertisements.fallback_locale', 'en');
    app()->setLocale('sk');

    $category = Category::query()->create([
        'name' => ['sk' => 'Vozidlá', 'en' => 'Vehicles'],
    ]);

    expect($category->slug)->toBe('vehicles');
});

it('rejects a manual slug another category already holds', function (): void {
    Category::factory()->create(['slug' => 'vehicles']);

    expect(fn () => Category::factory()->create(['slug' => 'vehicles']))
        ->toThrow(SlugAlreadyTakenException::class);
});

it('keeps its slug when the name is renamed', function (): void {
    $category = Category::factory()->create(['slug' => 'vehicles', 'name' => ['en' => 'Vehicles']]);

    $category->update(['name' => ['en' => 'Cars & Bikes']]);

    expect($category->fresh()?->slug)->toBe('vehicles');
});

it('scopes advertisements by a generated category slug', function (): void {
    $category = Category::query()->create(['name' => ['en' => 'Real Estate']]);
    $ad = Advertisement::factory()->create(['category_id' => $category->id]);

    expect($category->slug)->toBe('real-estate')
        ->and(Advertisement::query()->inCategory('real-estate')->pluck('id')->all())->toBe([$ad->id]);
});
