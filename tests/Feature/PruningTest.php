<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\Advertisements\Events\AdvertisementDeleted;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\MediaLibrary\Models\Media;
use RoundlyConsulting\Sluggable\Models\SlugHistory;

beforeEach(function (): void {
    Storage::fake('public');
});

it('prunes expired ads together with their creatives, rows and files', function (): void {
    $placement = Placement::factory()->create(['slug' => 'sidebar', 'width' => 300, 'height' => 250]);

    $expired = Advertisement::factory()->expired()->create();
    $creative = $expired->addCreative(UploadedFile::fake()->image('banner.jpg', 300, 250), $placement);
    $path = $creative->getPath();

    $live = Advertisement::factory()->published()->create();
    $kept = $live->addCreative(UploadedFile::fake()->image('live.jpg', 320, 260), $placement);

    Storage::disk('public')->assertExists($path);

    Artisan::call('model:prune', ['--model' => [Advertisement::class]]);

    expect(Advertisement::withTrashed()->find($expired->id))->toBeNull()
        ->and(Media::withTrashed()->where('model_id', $expired->id)->where('model_type', $expired->getMorphClass())->count())->toBe(0)
        ->and(Media::query()->whereKey($kept->getKey())->exists())->toBeTrue()
        ->and(Advertisement::query()->find($live->id))->not->toBeNull();

    Storage::disk('public')->assertMissing($path);
});

it('removes the creatives of a force-deleted ad and keeps them for a soft delete', function (): void {
    $placement = Placement::factory()->create(['slug' => 'sidebar', 'width' => 300, 'height' => 250]);
    $ad = Advertisement::factory()->published()->create();
    $ad->addCreative(UploadedFile::fake()->image('banner.jpg', 300, 250), $placement);

    $ad->delete();

    expect(Media::query()->where('model_id', $ad->id)->count())->toBe(1);

    $ad->forceDelete();

    expect(Media::withTrashed()->where('model_id', $ad->id)->count())->toBe(0);
});

it('fires the delete event and drops the slug history of a pruned ad', function (): void {
    config()->set('advertisements.slugs.history', true);

    $ad = Advertisement::factory()->expired()->create(['name' => 'Old Offer']);
    $ad->update(['name' => 'New Offer']);

    expect(SlugHistory::withTrashed()->where('sluggable_id', $ad->id)->count())->toBeGreaterThan(0);

    Event::fake([AdvertisementDeleted::class]);

    Artisan::call('model:prune', ['--model' => [Advertisement::class]]);

    Event::assertDispatched(AdvertisementDeleted::class, fn (AdvertisementDeleted $event): bool => $event->advertisement->is($ad));

    expect(SlugHistory::withTrashed()->where('sluggable_id', $ad->id)->count())->toBe(0);
});
