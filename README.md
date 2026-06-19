# Advertisements for Laravel

Manage advertisements, placements, and pricing for Laravel applications.

This package ships an `Advertisement` Eloquent model with a polymorphic author, automatic
slugging, soft deletes, prunable expiry, JSON metadata, and an immutable `Money` value object
for prices. Create and update advertisements through dedicated action classes that dispatch
events your application can listen to.

## Requirements

- PHP 8.3 or 8.4
- Laravel 12 or 13

## Installation

```bash
composer require roundly-consulting/advertisements-for-laravel
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="advertisements-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="advertisements-config"
```

## Configuration

The published config file (`config/advertisements.php`) exposes the model used by the package
so you can swap in your own subclass:

```php
return [
    /*
     * The Eloquent model used to store advertisements. Override this with your own
     * class (extending the package model) to customise behaviour.
     */
    'model' => RoundlyConsulting\Advertisements\Advertisement::class,
];
```

| Key | Type | Default | Purpose |
|---|---|---|---|
| `model` | `class-string` | `RoundlyConsulting\Advertisements\Advertisement::class` | The advertisement model resolved by `CreateAdvertisement`. |

## Usage

### Prices and the `Money` value object

Prices are represented by an immutable `Money` value object that stores the amount in **minor
units** (e.g. cents) and an ISO 4217 currency code. The model persists this across the
`price` (integer) and `currency` (string) columns:

```php
use RoundlyConsulting\Advertisements\ValueObjects\Money;

$money = new Money(amount: 1500, currency: 'EUR'); // €15.00

$money->getAmount();        // 1500
$money->getCurrency();      // "EUR"
$money->format('en_US');    // "€15.00"
(string) $money;            // "€15.00"

$money->equals(new Money(1500, 'EUR')); // true
```

### Creating an advertisement

```php
use Illuminate\Support\Collection;
use RoundlyConsulting\Advertisements\Actions\CreateAdvertisement;

$advertisement = app(CreateAdvertisement::class)->execute(
    name: 'Vintage road bike',
    price: 25000,            // 250.00 in minor units
    currency: 'EUR',
    category: 'bikes',
    description: 'Lightly used, great condition.',
    author: $user,          // any Eloquent model (polymorphic author)
    meta: new Collection(['featured' => true]),
    publishedAt: now(),
    expiresAt: now()->addMonth(),
);

$advertisement->slug;       // "vintage-road-bike" (unique, auto-generated)
$advertisement->price;      // Money instance
```

`name`, `price`, and `currency` are required; everything else is optional.

### Updating an advertisement

```php
use RoundlyConsulting\Advertisements\Actions\UpdateAdvertisement;

$advertisement = app(UpdateAdvertisement::class)->execute(
    advertisement: $advertisement,
    name: 'Vintage road bike (reduced)',
    price: 19900,
    currency: 'EUR',
);
```

Passing no `author` on update dissociates any existing author.

### Slugs

A unique slug is generated from `name` on save and regenerated whenever the name changes.
Collisions are resolved with a numeric suffix (`vintage-road-bike`, `vintage-road-bike-1`, …).

### Pruning expired advertisements

The model is `MassPrunable`; advertisements whose `expires_at` is in the past are pruned by
Laravel's scheduler:

```bash
php artisan model:prune --model="RoundlyConsulting\Advertisements\Advertisement"
```

### Events

Both actions dispatch events you can listen to:

```php
use RoundlyConsulting\Advertisements\Events\AdvertisementCreated;
use RoundlyConsulting\Advertisements\Events\AdvertisementUpdated;

Event::listen(AdvertisementCreated::class, function (AdvertisementCreated $event): void {
    $event->advertisement; // the created Advertisement
});

Event::listen(AdvertisementUpdated::class, function (AdvertisementUpdated $event): void {
    $event->advertisement; // the updated Advertisement
});
```

### Factory

A factory ships for testing and seeding, with `published()` and `expired()` states:

```php
use RoundlyConsulting\Advertisements\Advertisement;

Advertisement::factory()->create();
Advertisement::factory()->published()->create();
Advertisement::factory()->expired()->create();
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
