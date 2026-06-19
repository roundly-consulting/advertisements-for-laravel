# Advertisements for Laravel

Manage the full advertisement lifecycle — draft, schedule, publish, expire, and archive — in
your Laravel application.

This package ships an `Advertisement` Eloquent model with a polymorphic author, automatic
slugging, soft deletes, prunable expiry, JSON metadata, and an immutable `Money` value object
for prices. A first-class **status** concept, **lifecycle actions** (each dispatching an
event), expressive **query scopes**, and an `Advertisements` **facade** make the common paths
one fluent line, while every underlying action class stays injectable for DI-first code.

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

The published config file (`config/advertisements.php`):

```php
return [
    // The Eloquent model used to store advertisements. Override with your own
    // subclass to customise behaviour.
    'model' => RoundlyConsulting\Advertisements\Models\Advertisement::class,

    // ISO 4217 currency used when a bare amount is given without a currency.
    'default_currency' => env('ADVERTISEMENTS_CURRENCY', 'EUR'),

    // Register the `Advertisements` facade alias automatically.
    'register_facade_alias' => env('ADVERTISEMENTS_FACADE_ALIAS', true),
];
```

| Key | Type | Default | Purpose |
|---|---|---|---|
| `model` | `class-string` | `RoundlyConsulting\Advertisements\Models\Advertisement::class` | The advertisement model the package resolves. |
| `default_currency` | `string` | `EUR` (env `ADVERTISEMENTS_CURRENCY`) | Currency used by `AdvertisementData::fromAmount()` when none is supplied. |
| `register_facade_alias` | `bool` | `true` (env `ADVERTISEMENTS_FACADE_ALIAS`) | Whether to register the global `Advertisements` alias. Set to `false` to opt out. |

## Usage

### The `Advertisements` facade

```php
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\ValueObjects\Money;

$ad = Advertisements::create(new AdvertisementData(
    name: 'Vintage road bike',
    price: new Money(25000, 'EUR'), // 250.00 in minor units
    category: 'bikes',
    author: $user,                  // any Eloquent model (polymorphic author)
));

Advertisements::publish($ad);                  // live now → AdvertisementPublished
Advertisements::publish($ad, now()->addWeek()); // scheduled → AdvertisementPublished
Advertisements::unpublish($ad);                // back to draft → AdvertisementUnpublished
Advertisements::expire($ad);                   // → AdvertisementExpired
Advertisements::archive($ad);                  // → AdvertisementArchived
Advertisements::delete($ad);                   // soft delete → AdvertisementDeleted
```

The same operations are available as injectable action classes
(`RoundlyConsulting\Advertisements\Actions\*`), each with a single `execute()` method.

### The `AdvertisementData` DTO

Both `create` and `update` take an `AdvertisementData`. `name` and `price` are required;
everything else is optional:

```php
use Illuminate\Support\Collection;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Advertisements\ValueObjects\Money;

$data = new AdvertisementData(
    name: 'Vintage road bike',
    price: new Money(25000, 'EUR'),
    category: 'bikes',
    description: 'Lightly used, great condition.',
    author: $user,
    meta: new Collection(['featured' => true]),
    publishedAt: now(),
    expiresAt: now()->addMonth(),
);
```

Or build it from a bare amount, defaulting the currency to `default_currency`:

```php
$data = AdvertisementData::fromAmount(name: 'Vintage road bike', amount: 25000); // EUR
$data = AdvertisementData::fromAmount(name: 'Bike', amount: 25000, currency: 'USD');
```

### Updating an advertisement

```php
$ad = Advertisements::update($ad, new AdvertisementData(
    name: 'Vintage road bike (reduced)',
    price: new Money(19900, 'EUR'),
));
```

Passing no `author` on update dissociates any existing author.

### Status

Every advertisement has a `status` of type `RoundlyConsulting\Advertisements\Enums\AdvertisementStatus`:

| Case | Meaning |
|---|---|
| `Draft` | Created, not published. |
| `Scheduled` | `published_at` is in the future. |
| `Published` | Live now and not expired. |
| `Expired` | `expires_at` is in the past. |
| `Archived` | Explicitly archived. |

The `status` column is the stored source of truth, kept in sync by the lifecycle actions; the
`status` accessor overlays time-based expiry so a live ad past its `expires_at` reads as
`Expired` without a re-save.

```php
$ad->status; // AdvertisementStatus::Published
```

### Query scopes

```php
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Advertisement;

Advertisement::query()->published()->get();  // published_at reached, not archived
Advertisement::query()->active()->get();      // published AND not expired
Advertisement::query()->scheduled()->get();   // published_at in the future
Advertisement::query()->expired()->get();     // expires_at passed
Advertisement::query()->draft()->get();       // never published
Advertisement::query()->archived()->get();    // explicitly archived
Advertisement::query()->forAuthor($user)->get();

// The facade exposes a fresh builder for chaining:
Advertisements::query()->active()->get();
```

### Prices and the `Money` value object

`Money` stores the amount in **minor units** (e.g. cents) and an ISO 4217 currency code,
persisted across the `price` (integer) and `currency` (string) columns:

```php
use RoundlyConsulting\Advertisements\ValueObjects\Money;

$money = new Money(amount: 1500, currency: 'EUR'); // €15.00
$money->getAmount();     // 1500
$money->getCurrency();   // "EUR"
$money->format('en_US'); // "€15.00"
(string) $money;         // "€15.00"
$money->equals(new Money(1500, 'EUR')); // true
```

Assigning a non-`Money` value to `price` throws
`RoundlyConsulting\Advertisements\Exceptions\InvalidPrice` (which extends the package's base
`AdvertisementException`).

### Slugs

A unique slug is generated from `name` on save and regenerated whenever the name changes.
Collisions are resolved with a numeric suffix (`vintage-road-bike`, `vintage-road-bike-1`, …).

### Pruning expired advertisements

The model is `MassPrunable`; advertisements whose `expires_at` is in the past are pruned:

```bash
php artisan model:prune --model="RoundlyConsulting\Advertisements\Models\Advertisement"
```

### Events

Every create, update, and lifecycle transition dispatches an event you can listen to. Each
wraps the affected model in a public `$advertisement` property:

```php
use RoundlyConsulting\Advertisements\Events\AdvertisementCreated;
use RoundlyConsulting\Advertisements\Events\AdvertisementUpdated;
use RoundlyConsulting\Advertisements\Events\AdvertisementPublished;
use RoundlyConsulting\Advertisements\Events\AdvertisementUnpublished;
use RoundlyConsulting\Advertisements\Events\AdvertisementExpired;
use RoundlyConsulting\Advertisements\Events\AdvertisementArchived;
use RoundlyConsulting\Advertisements\Events\AdvertisementDeleted;

Event::listen(AdvertisementPublished::class, function (AdvertisementPublished $event): void {
    $event->advertisement; // the published Advertisement
});
```

### Factory

A factory ships for testing and seeding, with `published()`, `scheduled()`, `expired()`, and
`archived()` states:

```php
use RoundlyConsulting\Advertisements\Models\Advertisement;

Advertisement::factory()->create();
Advertisement::factory()->published()->create();
Advertisement::factory()->scheduled()->create();
Advertisement::factory()->expired()->create();
Advertisement::factory()->archived()->create();
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
