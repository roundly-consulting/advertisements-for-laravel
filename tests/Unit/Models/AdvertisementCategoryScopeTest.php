<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Category;

it('scopes by category model, id and slug', function (string $by): void {
    $category = Category::factory()->create(['slug' => 'bikes']);
    $ad = Advertisement::factory()->create(['category_id' => $category->id]);
    Advertisement::factory()->create();

    $reference = match ($by) {
        'model' => $category,
        'id' => $category->id,
        'slug' => 'bikes',
    };

    expect(Advertisement::query()->inCategory($reference)->pluck('id')->all())
        ->toBe([$ad->id]);
})->with(['model', 'id', 'slug']);

it('includes descendant categories when requested', function (): void {
    $vehicles = Category::factory()->create();
    $bikes = Category::factory()->create(['parent_id' => $vehicles->id]);

    $direct = Advertisement::factory()->create(['category_id' => $vehicles->id]);
    $nested = Advertisement::factory()->create(['category_id' => $bikes->id]);

    expect(Advertisement::query()->inCategory($vehicles)->pluck('id')->all())
        ->toBe([$direct->id]);

    expect(Advertisement::query()->inCategory($vehicles, includeDescendants: true)->pluck('id')->sort()->values()->all())
        ->toBe([$direct->id, $nested->id]);
});

it('resolves an unknown slug to null without creating a category', function (): void {
    Advertisement::factory()->create();

    expect(Advertisement::query()->inCategory('missing')->count())->toBe(0)
        ->and(Category::query()->count())->toBe(0);
});
