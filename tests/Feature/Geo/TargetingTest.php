<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Advertisements\Tests\Fixtures\ThrowingGeolocationProvider;
use RoundlyConsulting\Advertisements\ValueObjects\Targeting;
use RoundlyConsulting\Geolocation\DataTransferObjects\Coordinates;
use RoundlyConsulting\Geolocation\Facades\Geolocation;

function targetedAd(Placement $placement, ?Targeting $targeting = null): Advertisement
{
    $ad = Advertisement::factory()->published()->create();
    $ad->placements()->attach($placement);

    if ($targeting !== null) {
        $ad->targeting = $targeting;
        $ad->save();
    }

    return $ad;
}

beforeEach(function (): void {
    $this->placement = Placement::factory()->create();
});

it('returns untargeted ads for any viewer and a null viewer', function (): void {
    $ad = targetedAd($this->placement);

    $sk = Advertisements::targetedIn($this->placement, location('SK'))->pluck('id');
    $none = Advertisements::targetedIn($this->placement, null)->pluck('id');

    expect($sk)->toContain($ad->id)->and($none)->toContain($ad->id);
});

it('honours a country allow list', function (): void {
    $ad = targetedAd($this->placement, new Targeting(countries: ['SK']));

    expect(Advertisements::targetedIn($this->placement, location('SK'))->pluck('id'))->toContain($ad->id)
        ->and(Advertisements::targetedIn($this->placement, location('DE'))->pluck('id'))->not->toContain($ad->id);
});

it('honours a country deny list', function (): void {
    $ad = targetedAd($this->placement, new Targeting(excludeCountries: ['DE']));

    expect(Advertisements::targetedIn($this->placement, location('DE'))->pluck('id'))->not->toContain($ad->id)
        ->and(Advertisements::targetedIn($this->placement, location('SK'))->pluck('id'))->toContain($ad->id);
});

it('honours a radius around a centre point', function (): void {
    $bratislava = new Coordinates(48.1486, 17.1077);
    $ad = targetedAd($this->placement, new Targeting(center: $bratislava, radiusKm: 50.0));

    // ~10km away (Senec) versus ~330km away (Prague).
    $near = location('SK', 48.2190, 17.4000);
    $far = location('CZ', 50.0755, 14.4378);

    expect(Advertisements::targetedIn($this->placement, $near)->pluck('id'))->toContain($ad->id)
        ->and(Advertisements::targetedIn($this->placement, $far)->pluck('id'))->not->toContain($ad->id);
});

it('resolves the viewer from a request ip', function (): void {
    Geolocation::fake(['1.2.3.4' => location('SK')]);

    $ad = targetedAd($this->placement, new Targeting(countries: ['SK']));
    $blocked = targetedAd($this->placement, new Targeting(countries: ['DE']));

    $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '1.2.3.4']);

    $ids = Advertisements::targetedIn($this->placement, $request)->pluck('id');

    expect($ids)->toContain($ad->id)->not->toContain($blocked->id);
});

it('serves only untargeted ads when the viewer is unresolved', function (): void {
    Geolocation::fake();

    $untargeted = targetedAd($this->placement);
    $targeted = targetedAd($this->placement, new Targeting(countries: ['SK']));

    $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '9.9.9.9']);

    $ids = Advertisements::targetedIn($this->placement, $request)->pluck('id');

    expect($ids)->toContain($untargeted->id)->not->toContain($targeted->id);
});

it('serves all ads when match_when_unknown is all', function (): void {
    config()->set('advertisements.geo.match_when_unknown', 'all');
    Geolocation::fake();

    $untargeted = targetedAd($this->placement);
    $targeted = targetedAd($this->placement, new Targeting(countries: ['SK']));

    $ids = Advertisements::targetedIn($this->placement, null)->pluck('id');

    expect($ids)->toContain($untargeted->id)->toContain($targeted->id);
});

it('is identical to in() when targeting is disabled', function (): void {
    config()->set('advertisements.geo.targeting_enabled', false);

    $untargeted = targetedAd($this->placement);
    $targeted = targetedAd($this->placement, new Targeting(countries: ['SK']));

    $ids = Advertisements::targetedIn($this->placement, location('DE'))->pluck('id');

    expect($ids)->toContain($untargeted->id)->toContain($targeted->id);
});

/**
 * One ad per kind of rule, plus an untargeted one.
 *
 * @return array<string, Advertisement>
 */
function targetingMatrix(Placement $placement): array
{
    return [
        'untargeted' => targetedAd($placement),
        'excludeRu' => targetedAd($placement, new Targeting(excludeCountries: ['RU'])),
        'allowSk' => targetedAd($placement, new Targeting(countries: ['SK'])),
        'radius' => targetedAd($placement, new Targeting(center: new Coordinates(48.1486, 17.1077), radiusKm: 50.0)),
    ];
}

/**
 * @param  array<string, Advertisement>  $ads
 * @return list<string>
 */
function servedNames(array $ads, Collection $ids): array
{
    return array_keys(array_filter($ads, fn (Advertisement $ad): bool => $ids->contains($ad->id)));
}

it('treats a location that places nothing as an unknown viewer', function (): void {
    $ads = targetingMatrix($this->placement);
    $nowhere = location('');

    expect(servedNames($ads, Advertisements::targetedIn($this->placement, $nowhere)->pluck('id')))->toBe(['untargeted']);

    config()->set('advertisements.geo.match_when_unknown', 'all');

    expect(servedNames($ads, Advertisements::targetedIn($this->placement, $nowhere)->pluck('id')))
        ->toBe(['untargeted', 'excludeRu', 'allowSk', 'radius']);
});

it('serves every ad to an unresolved request ip when match_when_unknown is all', function (): void {
    config()->set('advertisements.geo.match_when_unknown', 'all');
    Geolocation::fake();
    $ads = targetingMatrix($this->placement);

    $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '203.0.113.9']);

    expect(servedNames($ads, Advertisements::targetedIn($this->placement, $request)->pluck('id')))
        ->toBe(['untargeted', 'excludeRu', 'allowSk', 'radius']);
});

it('does not serve a radius-only ad worldwide to a viewer known only by country', function (): void {
    $ads = targetingMatrix($this->placement);

    $ids = Advertisement::query()->forPlacement($this->placement)->targetedAt('US')->pluck('id');

    expect(servedNames($ads, $ids))->toBe(['untargeted', 'excludeRu']);
});

it('does not serve a country-restricted ad anywhere to a viewer known only by coordinates', function (): void {
    $ads = targetingMatrix($this->placement);

    $newYork = Advertisement::query()->forPlacement($this->placement)->targetedAt(new Coordinates(40.7128, -74.0060))->pluck('id');
    $senec = Advertisement::query()->forPlacement($this->placement)->targetedAt(new Coordinates(48.2190, 17.4000))->pluck('id');

    expect(servedNames($ads, $newYork))->toBe(['untargeted'])
        ->and(servedNames($ads, $senec))->toBe(['untargeted', 'radius']);
});

it('lets match_when_unknown decide a rule the viewer cannot be checked against', function (): void {
    config()->set('advertisements.geo.match_when_unknown', 'all');
    $ads = targetingMatrix($this->placement);

    $us = Advertisement::query()->forPlacement($this->placement)->targetedAt('US')->pluck('id');
    $senec = Advertisement::query()->forPlacement($this->placement)->targetedAt(location('', 48.2190, 17.4000))->pluck('id');

    expect(servedNames($ads, $us))->toBe(['untargeted', 'excludeRu', 'radius'])
        ->and(servedNames($ads, $senec))->toBe(['untargeted', 'excludeRu', 'allowSk', 'radius']);
});

it('checks both the country and the radius of a viewer that has both', function (): void {
    $both = targetedAd($this->placement, new Targeting(countries: ['SK'], center: new Coordinates(48.1486, 17.1077), radiusKm: 50.0));

    $vienna = location('AT', 48.2082, 16.3738); // ~55km away, wrong country
    $senec = location('SK', 48.2190, 17.4000);
    $kosice = location('SK', 48.7164, 21.2611); // right country, ~310km away

    expect(Advertisements::targetedIn($this->placement, $senec)->pluck('id'))->toContain($both->id)
        ->and(Advertisements::targetedIn($this->placement, $vienna)->pluck('id'))->not->toContain($both->id)
        ->and(Advertisements::targetedIn($this->placement, $kosice)->pluck('id'))->not->toContain($both->id);
});

it('serves as for an unknown viewer when the geolocation provider throws', function (): void {
    config()->set('geolocation.pipeline', ['broken']);
    config()->set('geolocation.providers.broken', ThrowingGeolocationProvider::class);
    $ads = targetingMatrix($this->placement);

    $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '8.8.8.8']);

    expect(servedNames($ads, Advertisements::targetedIn($this->placement, $request)->pluck('id')))->toBe(['untargeted']);
});
