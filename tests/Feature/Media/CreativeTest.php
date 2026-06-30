<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;

beforeEach(function (): void {
    Storage::fake('public');
});

it('attaches a creative for a placement and exposes it', function (): void {
    $ad = Advertisement::factory()->create();
    $placement = Placement::factory()->create(['width' => 300, 'height' => 250]);

    $ad->addCreative(UploadedFile::fake()->image('creative.jpg', 300, 250), $placement);

    expect($ad->creativeFor($placement))->not->toBeNull()
        ->and($ad->creativeUrl($placement))->not->toBe('');
});

it('isolates creatives per placement', function (): void {
    $ad = Advertisement::factory()->create();
    $a = Placement::factory()->create(['width' => 300, 'height' => 250]);
    $b = Placement::factory()->create(['width' => 728, 'height' => 90]);

    $ad->addCreative(UploadedFile::fake()->image('a.jpg', 300, 250), $a);

    expect($ad->creativeFor($a))->not->toBeNull()
        ->and($ad->creativeFor($b))->toBeNull();
});

it('keeps each placement bucket single-file by replacing the prior creative', function (): void {
    $ad = Advertisement::factory()->create();
    $placement = Placement::factory()->create(['width' => 300, 'height' => 250]);

    $ad->addCreative(UploadedFile::fake()->image('first.jpg', 300, 250), $placement);
    $second = $ad->addCreative(UploadedFile::fake()->image('second.jpg', 300, 250), $placement);

    expect($ad->getMedia($ad->creativeBucketName($placement)))->toHaveCount(1)
        ->and($ad->creativeFor($placement)?->getKey())->toBe($second->getKey());
});

it('returns the display variant url distinct from the original', function (): void {
    $ad = Advertisement::factory()->create();
    $placement = Placement::factory()->create(['width' => 300, 'height' => 250]);

    $ad->addCreative(UploadedFile::fake()->image('creative.jpg', 600, 500), $placement);

    expect($ad->creativeUrl($placement, 'display'))
        ->not->toBe($ad->creativeUrl($placement, ''));
});

it('does not crash for a placement without dimensions', function (): void {
    $ad = Advertisement::factory()->create();
    $placement = Placement::factory()->create(['width' => null, 'height' => null]);

    $ad->addCreative(UploadedFile::fake()->image('creative.jpg', 300, 250), $placement);

    expect($ad->creativeUrl($placement))->not->toBe('');
});

it('returns an empty creative url when no creative is attached', function (): void {
    $ad = Advertisement::factory()->create();
    $placement = Placement::factory()->create(['width' => 300, 'height' => 250]);

    expect($ad->creativeUrl($placement))->toBe('');
});

it('falls back to the generic creative bucket when no placement-specific creative exists', function (): void {
    $ad = Advertisement::factory()->create();
    $placement = Placement::factory()->create(['width' => 300, 'height' => 250]);

    $ad->addMedia(UploadedFile::fake()->image('generic.jpg', 300, 250))
        ->toMediaBucket($ad->fallbackCreativeBucket());

    expect($ad->creativeFor($placement))->not->toBeNull()
        ->and($ad->creativeUrl($placement))->not->toBe('');
});

it('does not fall back to the generic bucket when fallback is disabled', function (): void {
    config()->set('advertisements.media.use_fallback_bucket', false);

    $ad = Advertisement::factory()->create();
    $placement = Placement::factory()->create(['width' => 300, 'height' => 250]);

    $ad->addMedia(UploadedFile::fake()->image('generic.jpg', 300, 250))
        ->toMediaBucket($ad->fallbackCreativeBucket());

    expect($ad->creativeFor($placement))->toBeNull();
});

it('resolves a creative bucket by placement slug and id', function (): void {
    $ad = Advertisement::factory()->create();
    $placement = Placement::factory()->create(['slug' => 'sidebar', 'width' => 300, 'height' => 250]);

    $ad->addCreative(UploadedFile::fake()->image('creative.jpg', 300, 250), 'sidebar');

    expect($ad->creativeFor('sidebar'))->not->toBeNull()
        ->and($ad->creativeFor($placement->id))->not->toBeNull()
        ->and($ad->creativeBucketName('sidebar'))->toBe('creative:sidebar');
});
