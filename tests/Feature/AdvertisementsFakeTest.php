<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Advertisements\AdvertisementManager;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Exceptions\AdvertisementNotInPlacement;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Jobs\RecordAdvertisementEventJob;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Advertisements\Testing\AdvertisementsFake;
use RoundlyConsulting\Advertisements\Testing\RecordedEvent;
use RoundlyConsulting\Advertisements\Testing\RecordedPlacements;
use RoundlyConsulting\Money\Money;

/**
 * An ad running in a `sidebar` placement, ready to be tracked.
 */
function adInSidebar(): Advertisement
{
    $ad = Advertisement::factory()->create();
    $ad->placements()->attach(Placement::factory()->create(['slug' => 'sidebar']));

    return $ad;
}

it('is a manager subtype installed behind the facade and the container', function (): void {
    $fake = Advertisements::fake();

    expect($fake)->toBeInstanceOf(AdvertisementManager::class)
        ->and(app(AdvertisementManager::class))->toBe($fake)
        ->and(Advertisements::getFacadeRoot())->toBe($fake);
});

it('returns the same fake on repeated fake() calls', function (): void {
    expect(Advertisements::fake())->toBe(Advertisements::fake());
});

it('intercepts tracking, returns null and writes no rows or jobs', function (): void {
    Queue::fake();
    $fake = Advertisements::fake();
    $ad = adInSidebar();

    expect(Advertisements::for($ad)->track('sidebar')->impression())->toBeNull()
        ->and(Advertisements::for($ad)->track('sidebar')->click())->toBeNull()
        ->and(AdvertisementEvent::query()->count())->toBe(0)
        ->and($ad->refresh()->impressions_count)->toBe(0)
        ->and($ad->clicks_count)->toBe(0);

    Queue::assertNotPushed(RecordAdvertisementEventJob::class);
    $fake->assertImpressionRecorded();
    $fake->assertClickRecorded($ad);
});

it('matches tracking with a closure or an advertisement', function (): void {
    $fake = Advertisements::fake();
    $ad = adInSidebar();
    $other = Advertisement::factory()->create();

    Advertisements::for($ad)->track('sidebar')->impression();
    Advertisements::for($ad)->track()->click();

    $fake->assertImpressionRecorded(fn (RecordedEvent $event): bool => $event->ad->is($ad) && $event->placement === 'sidebar');
    $fake->assertClickRecorded(fn (RecordedEvent $event): bool => $event->placement === null);

    expect(fn () => $fake->assertImpressionRecorded($other))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertClickRecorded(fn (RecordedEvent $event): bool => $event->placement === 'sidebar'))
        ->toThrow(AssertionFailedError::class);
});

it('asserts nothing recorded, and fails once something was', function (): void {
    $fake = Advertisements::fake();
    $ad = adInSidebar();

    $fake->assertNothingRecorded();

    expect(fn () => $fake->assertImpressionRecorded())->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->assertClickRecorded())->toThrow(AssertionFailedError::class);

    Advertisements::for($ad)->track()->impression();

    expect(fn () => $fake->assertNothingRecorded())->toThrow(AssertionFailedError::class);
});

it('still refuses to track outside the ad\'s placements', function (): void {
    $fake = Advertisements::fake();
    $ad = Advertisement::factory()->create();
    Placement::factory()->create(['slug' => 'sidebar']);

    expect(fn () => Advertisements::for($ad)->track('sidebar'))->toThrow(AdvertisementNotInPlacement::class);

    $fake->assertNothingRecorded();
});

it('records and performs every lifecycle verb through the facade', function (string $verb, Closure $call): void {
    $fake = Advertisements::fake();
    $ad = Advertisement::factory()->published()->create();
    $other = Advertisement::factory()->create();

    $nothing = 'assertNothing'.ucfirst($verb);
    $assert = 'assert'.ucfirst($verb);

    $fake->{$nothing}();
    expect(fn () => $fake->{$assert}())->toThrow(AssertionFailedError::class);

    $call($ad);

    $fake->{$assert}();
    $fake->{$assert}($ad);
    $fake->{$assert}(fn (Advertisement $recorded): bool => $recorded->is($ad));

    expect(fn () => $fake->{$assert}($other))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->{$nothing}())->toThrow(AssertionFailedError::class);
})->with([
    'updated' => ['updated', fn (Advertisement $ad) => Advertisements::update($ad, new AdvertisementData(name: 'Renamed', price: Money::ofMinor(1, 'EUR')))],
    'published' => ['published', fn (Advertisement $ad) => Advertisements::publish($ad)],
    'unpublished' => ['unpublished', fn (Advertisement $ad) => Advertisements::unpublish($ad)],
    'expired' => ['expired', fn (Advertisement $ad) => Advertisements::expire($ad)],
    'archived' => ['archived', fn (Advertisement $ad) => Advertisements::archive($ad)],
    'deleted' => ['deleted', fn (Advertisement $ad) => Advertisements::delete($ad)],
]);

it('records creation and still persists it', function (): void {
    $fake = Advertisements::fake();

    $fake->assertNothingCreated();
    expect(fn () => $fake->assertCreated())->toThrow(AssertionFailedError::class);

    $ad = Advertisements::create(new AdvertisementData(name: 'Forwarded', price: Money::ofMinor(100, 'EUR')));

    expect(Advertisement::query()->whereKey($ad->getKey())->exists())->toBeTrue();
    $fake->assertCreated(fn (Advertisement $created): bool => $created->name === 'Forwarded');
    expect(fn () => $fake->assertNothingCreated())->toThrow(AssertionFailedError::class);
});

it('sees lifecycle calls made through the model methods', function (): void {
    $fake = Advertisements::fake();
    $ad = Advertisement::factory()->create();

    $ad->publish();
    $ad->unpublish();
    $ad->expire();
    $ad->archive();
    $ad->delete();

    $fake->assertPublished($ad);
    $fake->assertUnpublished($ad);
    $fake->assertExpired($ad);
    $fake->assertArchived($ad);
    $fake->assertDeleted($ad);
    expect($ad->trashed())->toBeTrue();
});

it('sees lifecycle calls made through an injected manager', function (): void {
    $fake = Advertisements::fake();
    $ad = Advertisement::factory()->create();

    app(AdvertisementManager::class)->publish($ad);

    $fake->assertPublished($ad);
});

it('records and performs placement changes', function (string $verb, Closure $call): void {
    $fake = Advertisements::fake();
    $ad = Advertisement::factory()->create();
    $other = Advertisement::factory()->create();
    $sidebar = Placement::factory()->create(['slug' => 'sidebar']);
    Placement::factory()->create(['slug' => 'header']);

    $nothing = 'assertNothing'.ucfirst($verb);
    $assert = 'assertPlacements'.ucfirst($verb);

    $fake->{$nothing}();
    expect(fn () => $fake->{$assert}())->toThrow(AssertionFailedError::class);

    $call($ad, $sidebar);

    $fake->{$assert}();
    $fake->{$assert}($ad);
    $fake->{$assert}(fn (RecordedPlacements $change): bool => $change->includes('sidebar') && $change->includes($sidebar));

    expect(fn () => $fake->{$assert}($other))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->{$assert}(fn (RecordedPlacements $change): bool => $change->includes('footer')))->toThrow(AssertionFailedError::class)
        ->and(fn () => $fake->{$nothing}())->toThrow(AssertionFailedError::class);
})->with([
    'attached' => ['attached', function (Advertisement $ad): void {
        Advertisements::for($ad)->placements()->attach(['sidebar', 'header']);
        expect($ad->placements()->count())->toBe(2);
    }],
    'detached' => ['detached', function (Advertisement $ad, Placement $sidebar): void {
        $ad->placements()->attach($sidebar);
        Advertisements::for($ad)->placements()->detach([$sidebar]);
        expect($ad->placements()->count())->toBe(0);
    }],
    'synced' => ['synced', function (Advertisement $ad, Placement $sidebar): void {
        Advertisements::for($ad)->placements()->sync(new ArrayIterator([$sidebar->id, 'sidebar']));
        expect($ad->placements()->count())->toBe(1);
    }],
]);

it('matches a recorded placement change by model, id or slug', function (): void {
    $sidebar = Placement::factory()->create(['slug' => 'sidebar']);
    $ad = Advertisement::factory()->create();

    $bySlug = new RecordedPlacements($ad, ['sidebar']);
    $byModel = new RecordedPlacements($ad, [$sidebar]);
    $byId = new RecordedPlacements($ad, [$sidebar->id]);

    expect($bySlug->includes($sidebar))->toBeTrue()
        ->and($byModel->includes('sidebar'))->toBeTrue()
        ->and($byModel->includes($sidebar->id))->toBeTrue()
        ->and($byId->includes($sidebar))->toBeTrue()
        ->and($byId->includes('sidebar'))->toBeFalse()
        ->and($bySlug->includes('header'))->toBeFalse();
});

it('is built through the container with the parent constructor', function (): void {
    expect(app(AdvertisementsFake::class)->query()->getModel())->toBeInstanceOf(Advertisement::class);
});
