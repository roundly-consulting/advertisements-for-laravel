<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use RoundlyConsulting\Advertisements\Actions\CreateAdvertisement;
use RoundlyConsulting\Advertisements\AdvertisementManager;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Money\Money;

it('pins the facade contract', function (): void {
    expect(Advertisements::class)
        ->toDocumentItsRoot()
        ->toBeFakeable()
        ->toReachEveryAction(__DIR__.'/../../src/Actions');
});

it('works without the facade through the injected manager', function (): void {
    $manager = app(AdvertisementManager::class);
    Placement::factory()->create(['slug' => 'sidebar']);

    $ad = $manager->create(new AdvertisementData(name: 'Injected', price: Money::ofMinor(100, 'EUR')));
    $manager->for($ad)->placements()->attach(['sidebar']);
    $manager->publish($ad);

    expect($manager)->toBe(Advertisements::getFacadeRoot())
        ->and($manager->in('sidebar')->pluck('id')->all())->toBe([$ad->id])
        ->and($manager->for($ad)->track('sidebar')->impression()?->advertisement_id)->toBe($ad->id);
});

it('runs the same use case as the raw action', function (): void {
    $ad = app(CreateAdvertisement::class)->execute(new AdvertisementData(name: 'Raw', price: Money::ofMinor(100, 'EUR')));

    expect($ad)->toBeInstanceOf(Advertisement::class)
        ->and(Advertisements::query()->whereKey($ad->getKey())->exists())->toBeTrue();
});

it('renders a creative through the facade and the model sugar alike', function (): void {
    Storage::fake('public');
    $ad = Advertisement::factory()->create(['name' => 'Buy widgets']);
    $placement = Placement::factory()->create(['width' => 300, 'height' => 250]);

    $html = Advertisements::render($ad, $placement, ['class' => 'ad']);

    expect($html)->toBeInstanceOf(HtmlString::class)
        ->and((string) $html)->toContain('Buy widgets')
        ->and((string) $html)->toBe((string) $ad->renderCreative($placement, ['class' => 'ad']));
});

it('resolves every action through the container, so a host binding applies', function (): void {
    $replacement = new class
    {
        public ?AdvertisementData $seen = null;

        public function execute(AdvertisementData $data): Advertisement
        {
            $this->seen = $data;

            return new Advertisement;
        }
    };

    app()->instance(CreateAdvertisement::class, $replacement);

    Advertisements::create($data = new AdvertisementData(name: 'Bound', price: Money::ofMinor(1, 'EUR')));

    expect($replacement->seen)->toBe($data);
});
