<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;
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
