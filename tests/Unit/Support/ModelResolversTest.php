<?php

declare(strict_types=1);

use RoundlyConsulting\Advertisements\Models\Advertisement;
use RoundlyConsulting\Advertisements\Models\AdvertisementEvent;
use RoundlyConsulting\Advertisements\Models\Category;
use RoundlyConsulting\Advertisements\Models\Placement;
use RoundlyConsulting\Advertisements\Support\AdvertisementModel;
use RoundlyConsulting\Advertisements\Support\CategoryModel;
use RoundlyConsulting\Advertisements\Support\EventModel;
use RoundlyConsulting\Advertisements\Support\PlacementModel;
use RoundlyConsulting\Advertisements\Tests\Models\CustomAdvertisement;
use RoundlyConsulting\Advertisements\Tests\Models\CustomAdvertisementEvent;
use RoundlyConsulting\Advertisements\Tests\Models\CustomCategory;
use RoundlyConsulting\Advertisements\Tests\Models\CustomPlacement;
use RoundlyConsulting\MediaLibrary\Models\Media;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

dataset('resolvers', [
    'advertisement' => [AdvertisementModel::class, 'advertisements.model', Advertisement::class, CustomAdvertisement::class],
    'placement' => [PlacementModel::class, 'advertisements.placement_model', Placement::class, CustomPlacement::class],
    'category' => [CategoryModel::class, 'advertisements.category_model', Category::class, CustomCategory::class],
    'event' => [EventModel::class, 'advertisements.event_model', AdvertisementEvent::class, CustomAdvertisementEvent::class],
]);

it('resolves the packaged model by default', function (string $resolver, string $key, string $packaged): void {
    expect($resolver::class())->toBe($packaged)
        ->and($resolver::new())->toBeInstanceOf($packaged)
        ->and($resolver::query()->getModel())->toBeInstanceOf($packaged);
})->with('resolvers');

it('honours a host subclass', function (string $resolver, string $key, string $packaged, string $custom): void {
    config()->set($key, $custom);

    expect($resolver::class())->toBe($custom)
        ->and($resolver::new())->toBeInstanceOf($custom);
})->with('resolvers');

it('refuses a foreign model instead of falling back to the packaged one', function (string $resolver, string $key, string $packaged): void {
    // The toolkit refuses any class that is not the packaged model or a subclass of it.
    config()->set($key, Media::class);

    expect(fn (): string => $resolver::class())->toThrow(
        InvalidConfigurationException::class,
        "Configuration value [{$key}] must be a class-string of [{$packaged}], [".Media::class.'] given.',
    );
})->with('resolvers');

it('throws when the configured value is not a model at all', function (string $resolver, string $key): void {
    config()->set($key, 'NotAClass');

    $resolver::class();
})->with('resolvers')->throws(InvalidConfigurationException::class);
