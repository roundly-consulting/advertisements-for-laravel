<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Category;
use RoundlyConsulting\Advertisements\Support\CategoryResolver;

it('resolves a model, id, slug and numeric string to a key', function (): void {
    $category = Category::factory()->create(['slug' => 'bikes']);
    $resolver = app(CategoryResolver::class);

    expect($resolver->resolveKey($category))->toBe($category->id)
        ->and($resolver->resolveKey($category->id))->toBe($category->id)
        ->and($resolver->resolveKey('bikes'))->toBe($category->id)
        ->and($resolver->resolveKey((string) $category->id))->toBe($category->id)
        ->and($resolver->resolveKey('999999'))->toBeNull()
        ->and($resolver->resolveKey('missing'))->toBeNull()
        ->and($resolver->resolveKey(null))->toBeNull();
});

it('prefers a category whose slug is the numeric string over the id', function (): void {
    $byId = Category::factory()->create(['slug' => 'bikes']);
    $bySlug = Category::factory()->create(['slug' => (string) $byId->id]);

    expect(app(CategoryResolver::class)->resolveKey((string) $byId->id))->toBe($bySlug->id);
});

it('categorises and scopes an ad by a numeric-string category id', function (): void {
    $category = Category::factory()->create(['slug' => 'bikes']);

    $ad = Advertisements::create(new AdvertisementData(name: 'Bike', category: (string) $category->id));

    expect($ad->fresh()?->category_id)->toBe($category->id)
        ->and(Advertisement::query()->inCategory((string) $category->id)->pluck('id')->all())->toBe([$ad->id]);
});
