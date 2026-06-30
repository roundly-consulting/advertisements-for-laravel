<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/advertisements-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=advertisements-for-laravel">
    <img src="art/hero.png" alt="Advertisements for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

# Advertisements for Laravel

Manage the full advertisement lifecycle — draft, schedule, publish, expire, and archive — in
your Laravel application.

This package ships an `Advertisement` Eloquent model with a polymorphic author, automatic
slugging, soft deletes, prunable expiry, JSON metadata, and an immutable `Money` value object
for prices. A first-class **status** concept, **lifecycle actions** (each dispatching an
event), expressive **query scopes**, and an `Advertisements` **facade** make the common paths
one fluent line, while every underlying action class stays injectable for DI-first code.

It also turns ads into an **ad-serving engine**: assign ads to **placements** (zones),
**track impressions and clicks** (synchronously or buffered to the queue) with denormalized
counters and click-through rate, store **per-locale translatable** name/description/slug
(no third-party dependency), organise ads into a nestable **category** tree, bind routes by
**slug**, and assert on tracking in tests with `Advertisements::fake()`.

On top of that it integrates two sibling packages (see **Integrates with** below): visual
**creatives per placement** (a responsive image sized to each zone, with a **text-ad
fallback**) via `media-library-for-laravel`, and **geo-targeting** (serve ads by the viewer's
country / radius) plus **geo-reported impressions** (per-country reporting) via
`geolocation-for-laravel`.

## Requirements

- PHP 8.4
- Laravel 12 or 13
- `roundly-consulting/media-library-for-laravel` and `roundly-consulting/geolocation-for-laravel`
  (installed automatically as dependencies)

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

Optionally publish the text-ad Blade view to restyle the text-ad fallback:

```bash
php artisan vendor:publish --tag="advertisements-views"
```

Creatives are stored through `media-library-for-laravel` — configure its `media.disk` and an
image driver (`gd`/`imagick`) per that package's README. Geo features resolve viewer IPs
through `geolocation-for-laravel`; configure its provider pipeline (MaxMind, IPinfo, …) per
that package's README. Both register automatically.

## Configuration

The published config file (`config/advertisements.php`):

```php
return [
    // Eloquent models the package resolves. Override with your own subclasses.
    'model' => RoundlyConsulting\Advertisements\Models\Advertisement::class,
    'placement_model' => RoundlyConsulting\Advertisements\Models\Placement::class,
    'category_model' => RoundlyConsulting\Advertisements\Models\Category::class,
    'event_model' => RoundlyConsulting\Advertisements\Models\AdvertisementEvent::class,

    // ISO 4217 currency used when a bare amount is given without a currency.
    'default_currency' => env('ADVERTISEMENTS_CURRENCY', 'EUR'),

    // Locale used when the active locale has no translation for an attribute.
    'fallback_locale' => env('ADVERTISEMENTS_FALLBACK_LOCALE', config('app.fallback_locale', 'en')),

    // Impression / click recording. When "buffered" is true, recording is
    // dispatched to the queue instead of being written inline.
    'tracking' => [
        'buffered' => env('ADVERTISEMENTS_TRACKING_BUFFERED', false),
        'queue' => env('ADVERTISEMENTS_TRACKING_QUEUE'),
        'connection' => env('ADVERTISEMENTS_TRACKING_CONNECTION'),
    ],

    // Register the `Advertisements` facade alias automatically.
    'register_facade_alias' => env('ADVERTISEMENTS_FACADE_ALIAS', true),

    // Visual creatives (media-library): one single-file bucket per placement named
    // "{creative_bucket_prefix}:{placement-slug}" plus a generic "{fallback_bucket}".
    'media' => [
        'creative_bucket_prefix' => 'creative',
        'fallback_bucket' => 'creative',
        'disk' => env('ADVERTISEMENTS_MEDIA_DISK'), // null = media-library default
        'responsive_widths' => null,                // null = media-library default ladder
        'display_variant' => 'display',
        'use_fallback_bucket' => true,
        'text_ad_view' => 'advertisements::text-ad',
    ],

    // Geo-targeting & geo reporting (geolocation).
    'geo' => [
        'targeting_enabled' => env('ADVERTISEMENTS_GEO_TARGETING', true),
        'untargeted_match' => true,                 // ads with no targeting match every viewer
        'match_when_unknown' => 'untargeted_only',  // unresolved viewer: 'untargeted_only' | 'all'
        'stamp_events' => env('ADVERTISEMENTS_GEO_STAMP', true),
    ],
];
```

| Key | Type | Default | Purpose |
|---|---|---|---|
| `model` | `class-string` | `…\Models\Advertisement::class` | The advertisement model the package resolves. |
| `placement_model` | `class-string` | `…\Models\Placement::class` | The placement (zone) model. |
| `category_model` | `class-string` | `…\Models\Category::class` | The category model. |
| `event_model` | `class-string` | `…\Models\AdvertisementEvent::class` | The impression/click event model. |
| `default_currency` | `string` | `EUR` (env `ADVERTISEMENTS_CURRENCY`) | Currency used by `AdvertisementData::fromAmount()` when none is supplied. |
| `fallback_locale` | `string` | app fallback (env `ADVERTISEMENTS_FALLBACK_LOCALE`) | Locale used when a translatable attribute has no value for the active locale. |
| `tracking.buffered` | `bool` | `false` (env `ADVERTISEMENTS_TRACKING_BUFFERED`) | Dispatch recording to the queue instead of writing inline. |
| `tracking.queue` | `?string` | `null` (env `ADVERTISEMENTS_TRACKING_QUEUE`) | Queue name for buffered recording (`null` = default). |
| `tracking.connection` | `?string` | `null` (env `ADVERTISEMENTS_TRACKING_CONNECTION`) | Queue connection for buffered recording (`null` = default). |
| `register_facade_alias` | `bool` | `true` (env `ADVERTISEMENTS_FACADE_ALIAS`) | Whether to register the global `Advertisements` alias. Set to `false` to opt out. |
| `media.creative_bucket_prefix` | `string` | `creative` | Prefix for the per-placement bucket name (`{prefix}:{slug}`). |
| `media.fallback_bucket` | `string` | `creative` | Generic, size-less creative bucket tried before the text-ad fallback. |
| `media.disk` | `?string` | `null` (env `ADVERTISEMENTS_MEDIA_DISK`) | Disk for creatives (`null` = media-library default). |
| `media.responsive_widths` | `?list<int>` | `null` | Responsive width ladder (`null` = media-library default). |
| `media.display_variant` | `string` | `display` | Variant name fit to the placement dimensions. |
| `media.use_fallback_bucket` | `bool` | `true` | Try the generic creative bucket before the text ad. |
| `media.text_ad_view` | `string` | `advertisements::text-ad` | Blade view rendering the text-ad fallback. |
| `geo.targeting_enabled` | `bool` | `true` (env `ADVERTISEMENTS_GEO_TARGETING`) | Apply geo targeting in `targetedFor()`. |
| `geo.untargeted_match` | `bool` | `true` | Ads with no targeting match every viewer. |
| `geo.match_when_unknown` | `string` | `untargeted_only` | Serving when the viewer is unresolved: `untargeted_only` or `all`. |
| `geo.stamp_events` | `bool` | `true` (env `ADVERTISEMENTS_GEO_STAMP`) | Stamp the viewer country (+ region/city/coords) onto recorded events. |

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
    category: $bikesCategory, // a Category model, an id, or a slug — resolved to category_id
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

### Placements (zones)

Model where an ad renders — sidebar, header, in-feed — and serve the live ads for a zone in
one call:

```php
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Placement;

$sidebar = Placement::factory()->create(['slug' => 'sidebar', 'name' => ['en' => 'Sidebar']]);

Advertisements::attachPlacements($ad, ['sidebar', $headerId]); // models, ids, or slugs
Advertisements::syncPlacements($ad, ['sidebar']);
Advertisements::detachPlacements($ad, ['sidebar']);

// Active ads in a placement, or one at random:
Advertisements::for('sidebar')->get();
Advertisements::random('sidebar');

Advertisement::query()->forPlacement($sidebar)->get(); // model, id, or slug
```

### Impression & click tracking

Record impressions and clicks — synchronously, or buffered to the queue when
`tracking.buffered` is true. Both paths write an `advertisement_events` row, bump a
denormalized counter, and fire an event:

```php
use RoundlyConsulting\Advertisements\DataTransferObjects\ImpressionData;
use RoundlyConsulting\Advertisements\Facades\Advertisements;

Advertisements::recordImpression($ad, 'sidebar');
Advertisements::recordClick($ad, 'sidebar', new ImpressionData(
    ip: $request->ip(),
    userAgent: $request->userAgent(),
    referrer: $request->headers->get('referer'),
));

$ad->impressions(); // int  (impressions_count)
$ad->clicks();      // int  (clicks_count)
$ad->ctr();         // float 0.0–1.0
$ad->events;        // HasMany<AdvertisementEvent>
```

Listen for `ImpressionRecorded` / `ClickRecorded` (each exposes a public `$event`
`AdvertisementEvent`). With buffering on, recording returns a `PendingDispatch` and runs via
`RecordAdvertisementEventJob` on the configured connection/queue.

### Translations

`name`, `description`, and `slug` are per-locale JSON maps via the in-package
`Concerns\HasTranslations` trait (no third-party dependency). Reading resolves the active
locale, then `fallback_locale`, then any stored value:

```php
$ad->setTranslation('name', 'de', 'Vintage Fahrrad')->save();

app()->setLocale('de');
$ad->name;                          // "Vintage Fahrrad"
$ad->getTranslation('name', 'en');  // "Vintage Bike"
$ad->getTranslations('name');       // ['en' => 'Vintage Bike', 'de' => 'Vintage Fahrrad']
```

Assigning a bare string (`$ad->name = 'Bike'`) stores it under the current locale, so
single-locale code keeps working.

### Categories

Organise ads into a nestable category tree:

```php
use RoundlyConsulting\Advertisements\Models\Category;

$vehicles = Category::factory()->create(['slug' => 'vehicles', 'name' => ['en' => 'Vehicles']]);
$bikes = Category::factory()->create(['parent_id' => $vehicles->id]);

$ad->category;            // BelongsTo<Category>
$vehicles->children;      // HasMany<Category>
$vehicles->descendants(); // every nested category

Advertisement::query()->inCategory($vehicles)->get();                          // direct only
Advertisement::query()->inCategory($vehicles, includeDescendants: true)->get(); // + nested
```

`inCategory()` accepts a model, id, or slug; an unknown slug matches nothing.

### Slugs and route binding

A unique slug is generated **per locale** from that locale's `name` on save and regenerated
when the name changes; same-locale collisions get a numeric suffix (`vintage-road-bike`,
`vintage-road-bike-1`, …), while different locales may share a slug. Advertisements bind by
slug, matching the active locale first and then the fallback locale:

```php
// routes/web.php
Route::get('/ads/{advertisement}', fn (Advertisement $advertisement) => $advertisement);
// GET /ads/vintage-road-bike resolves the ad whose current-locale slug matches.
```

### Convenience methods

```php
$ad->isPublished(); $ad->isActive(); $ad->isExpired(); $ad->isScheduled(); $ad->isArchived();

$ad->publish();   // same action + event as Advertisements::publish($ad)
$ad->unpublish(); $ad->expire(); $ad->archive(); $ad->delete();

$a->add($b); $a->subtract($b); $a->isZero(); // Money math; mismatched currencies throw InvalidPrice
```

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

### Testing helpers

`Advertisements::fake()` swaps the manager for a fake that records impressions and clicks in
memory (no DB writes) and forwards everything else to the real manager:

```php
use RoundlyConsulting\Advertisements\Facades\Advertisements;

$fake = Advertisements::fake();

Advertisements::recordImpression($ad, 'sidebar');

$fake->assertImpressionRecorded();
$fake->assertImpressionRecorded(fn ($e) => $e->ad->is($ad) && $e->placement === 'sidebar');
$fake->assertClickRecorded();      // optional closure filter
$fake->assertNothingRecorded();
```

### Creatives & media

Each placement has its own single-file creative bucket with a `display` variant fit to that
placement's `width`×`height`. Attach an image and read it back, or render it (an image when one
exists, the text ad otherwise):

```php
use Illuminate\Http\UploadedFile;

// Attach a creative for a placement (model, id, or slug).
$ad->addCreative(UploadedFile::fake()->image('banner.jpg', 300, 250), 'sidebar');

$ad->creativeFor('sidebar');                 // ?Media (placement-specific, then generic)
$ad->creativeUrl('sidebar');                 // display-variant URL, or '' / fallback when empty
$ad->creativeUrl('sidebar', '');             // the original

// Responsive <img> when a creative exists, else the text-ad fallback (name + description + price).
echo $ad->renderCreative('sidebar', ['class' => 'ad']);
```

The text ad renders through the publishable `advertisements::text-ad` Blade view, so hosts can
restyle it. Bind your own `RoundlyConsulting\Advertisements\Contracts\CreativeRenderer` to swap
the whole resolution chain.

### Geo-targeting

Ads declare targeting (allowed/blocked countries and/or a radius around a point) via a
`Targeting` value object. At serve time, resolve the active ads for a placement that match the
viewer's location:

```php
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\ValueObjects\Targeting;
use RoundlyConsulting\Geolocation\DataTransferObjects\Coordinates;

$ad->targeting = new Targeting(
    countries: ['SK', 'CZ'],                 // allow list (empty = any)
    excludeCountries: ['RU'],                // deny list
    center: new Coordinates(48.1486, 17.1077),
    radiusKm: 50.0,
);
$ad->save();

// Resolve the viewer from the request IP (geolocation), or pass a Location / null:
$ads = Advertisements::targetedFor('sidebar', request())->get();

// Or the model scope directly:
Advertisement::query()->active()->forPlacement('sidebar')->targetedAt($location)->get();
```

Untargeted ads match every viewer (configurable). When the viewer cannot be resolved, only
untargeted ads are served (or all, per `geo.match_when_unknown`).

### Geo-reported impressions

When `geo.stamp_events` is on, recording stamps the viewer's country (indexed `country_code`
column) plus region/city/coords (in the event `meta`) from the impression IP, for per-country
reporting:

```php
use RoundlyConsulting\Advertisements\DataTransferObjects\ImpressionData;

Advertisements::recordImpression($ad, 'sidebar', new ImpressionData(ip: $request->ip()));

$ad->impressionsByCountry(); // ['SK' => 1240, 'CZ' => 310]
$ad->clicksByCountry();      // ['SK' => 58]
```

Resolution failures never abort the record — the event is saved with a null country.

## Integrates with

This package builds directly on two sibling roundly-consulting packages (hard `require`s, wired
per the org's [cross-package integration plan](../docs/cross-package-integration-plan.md)):

- **[media-library-for-laravel](https://github.com/roundly-consulting/media-library-for-laravel)**
  — per-placement creatives, responsive variants, the `display` variant, and the text-ad fallback.
- **[geolocation-for-laravel](https://github.com/roundly-consulting/geolocation-for-laravel)**
  — viewer location for geo-targeting and the geo-stamped, per-country impression reports.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
