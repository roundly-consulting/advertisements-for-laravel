<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/advertisements-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=advertisements-for-laravel">
    <img src="art/hero.png" alt="Advertisements for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/advertisements-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/advertisements-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/advertisements-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/advertisements-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/advertisements-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/advertisements-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=advertisements-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Advertisements for Laravel

Manage the full advertisement lifecycle — draft, schedule, publish, expire, and archive — in
your Laravel application.

This package ships an `Advertisement` Eloquent model with a polymorphic author, automatic
slugging, soft deletes, prunable expiry, JSON metadata, and exact, arbitrary-precision
`Money` prices. A first-class **status** concept, **lifecycle actions** (each dispatching an
event), expressive **query scopes**, and an `Advertisements` **facade** make the common paths
one fluent line, while every underlying action class stays injectable for DI-first code.

It also turns ads into an **ad-serving engine**: assign ads to **placements** (zones),
**track impressions and clicks** (synchronously or buffered to the queue) with denormalized
counters and click-through rate, store **per-locale translatable** name/description/slug
(no third-party dependency), organise ads into a nestable **category** tree, bind routes by
**slug**, and assert on tracking in tests with `Advertisements::fake()`.

On top of that it integrates four sibling packages (see **Integrates with** below): visual
**creatives per placement** (a responsive image sized to each zone, with a **text-ad
fallback**) via `media-library-for-laravel`, **geo-targeting** (serve ads by the viewer's
country / radius) plus **geo-reported impressions** (per-country reporting) via
`geolocation-for-laravel`, and **per-locale slugs** with database-enforced uniqueness, any-locale
route binding and optional 301 redirects from retired slugs via `sluggable-for-laravel`, and
**exact prices** — minor-unit `Money` in a `decimal(38,0)` column, no float anywhere — via
`money-for-laravel`.

## Requirements

- PHP 8.4 with `ext-bcmath`
- Laravel 12 or 13
- `roundly-consulting/media-library-for-laravel`, `roundly-consulting/geolocation-for-laravel`,
  `roundly-consulting/sluggable-for-laravel` and `roundly-consulting/money-for-laravel`
  (installed automatically as dependencies)

## Installation

```bash
composer require roundly-consulting/advertisements-for-laravel
```

Publish and run the migrations (if the models that author ads use UUID or ULID keys, set
`ADVERTISEMENTS_KEY_TYPE=uuid` / `ulid` first — see `key_type` under [Configuration](#configuration)):

```bash
php artisan vendor:publish --tag="advertisements-migrations"
php artisan migrate
```

The migrations are **publish-only** — the package never loads them itself, so `php artisan
migrate` runs exactly the seven files you published (timestamped in dependency order:
categories → placements → advertisements → the pivot → events). Creatives also need
`media-library-for-laravel`'s own migrations (`vendor:publish --tag="media-migrations"`); slug
history (off by default) needs `sluggable-for-laravel`'s (`vendor:publish --tag="sluggable-migrations"`).
The `price` column comes from `money-for-laravel`'s `$table->money()` macro — a `decimal(38,0)`
minor-unit amount plus a `currency` column sized by `money.schema.currency_length` — so money's
service provider must be registered when you migrate (auto-discovery does it).

The advertisements migration creates one unique slug index **per supported locale** — the
locales `sluggable-for-laravel` reports at migrate time (`sluggable.locales.supported`, else
`app.locale` + `app.fallback_locale`). Set that list before migrating; add a locale later with
`php artisan sluggable:indexes "RoundlyConsulting\Advertisements\Models\Advertisement"`.

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

    // Key type of the polymorphic author column: 'bigint' | 'uuid' | 'ulid'. Read by the
    // migration — set it before you migrate.
    'key_type' => env('ADVERTISEMENTS_KEY_TYPE', 'bigint'),

    // Currency used by AdvertisementData::fromMinor() / ::fromDecimal() when none is given.
    'default_currency' => env('ADVERTISEMENTS_CURRENCY', 'EUR'),

    // Locale used when the active locale has no translation for an attribute.
    'fallback_locale' => env('ADVERTISEMENTS_FALLBACK_LOCALE', config('app.fallback_locale', 'en')),

    // Slugs (sluggable): keep retired advertisement slugs and 301 them to the current one.
    'slugs' => [
        'history' => env('ADVERTISEMENTS_SLUG_HISTORY', false),
    ],

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
        'match_when_unknown' => 'untargeted_only',  // unknown viewer / unknown fact: 'untargeted_only' | 'all'
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
| `key_type` | `string` | `bigint` (env `ADVERTISEMENTS_KEY_TYPE`) | Key type of the polymorphic `author_id` column: `bigint`, `uuid` or `ulid` (anything else throws an `InvalidConfigurationException`). The advertisements migration reads it, so set it **before migrating** when your author models use UUID/ULID keys; every author model must share that key type. Changing it later needs a migration of your own. |
| `default_currency` | `string` | `EUR` (env `ADVERTISEMENTS_CURRENCY`) | Currency used by `AdvertisementData::fromMinor()` / `fromDecimal()` when none is supplied. Must be registered in money-for-laravel's currency registry. |
| `fallback_locale` | `string` | app fallback (env `ADVERTISEMENTS_FALLBACK_LOCALE`) | Locale used when a translatable attribute has no value for the active locale; also the slug binding fallback and the name locale category/placement slugs are generated from. Not set (`null` or blank) means no translation fallback, and slugs use `en`. |
| `slugs.history` | `bool` | `false` (env `ADVERTISEMENTS_SLUG_HISTORY`) | Keep retired advertisement slugs and redirect (301) them to the current slug. Needs sluggable's migration. |
| `tracking.buffered` | `bool` | `false` (env `ADVERTISEMENTS_TRACKING_BUFFERED`) | Dispatch recording to the queue instead of writing inline. |
| `tracking.queue` | `?string` | `null` (env `ADVERTISEMENTS_TRACKING_QUEUE`) | Queue name for buffered recording (`null` = default). |
| `tracking.connection` | `?string` | `null` (env `ADVERTISEMENTS_TRACKING_CONNECTION`) | Queue connection for buffered recording (`null` = default). |
| `register_facade_alias` | `bool` | `true` (env `ADVERTISEMENTS_FACADE_ALIAS`) | Whether to register the global `Advertisements` alias. Set to `false` to opt out. |
| `media.creative_bucket_prefix` | `string` | `creative` | Prefix for the per-placement bucket name (`{prefix}:{slug}`). |
| `media.fallback_bucket` | `string` | `creative` | Generic, size-less creative bucket tried before the text-ad fallback. |
| `media.disk` | `?string` | `null` (env `ADVERTISEMENTS_MEDIA_DISK`) | Disk for creatives (`null` = media-library default). |
| `media.responsive_widths` | `?list<int>` | `null` | Responsive width ladder (`null` = media-library default). Every width must be a positive integer; a bad entry throws instead of being dropped. |
| `media.display_variant` | `string` | `display` | Variant name fit to the placement dimensions. |
| `media.use_fallback_bucket` | `bool` | `true` | Try the generic creative bucket before the text ad. |
| `media.text_ad_view` | `string` | `advertisements::text-ad` | Blade view rendering the text-ad fallback. |
| `geo.targeting_enabled` | `bool` | `true` (env `ADVERTISEMENTS_GEO_TARGETING`) | Apply geo targeting in `targetedIn()`. |
| `geo.untargeted_match` | `bool` | `true` | Ads with no targeting match every viewer. |
| `geo.match_when_unknown` | `string` | `untargeted_only` | What an unknown viewer gets — `untargeted_only` or `all` — and whether a rule the viewer lacks the country or coordinates for fails or passes (see [Geo-targeting](#geo-targeting)). Anything else (`ALL`, `everything`) throws an `InvalidConfigurationException` listing both. |
| `geo.stamp_events` | `bool` | `true` (env `ADVERTISEMENTS_GEO_STAMP`) | Stamp the viewer country (+ region/city/coords) onto recorded events. |

Every `bool` key accepts `true`/`false`, `1`/`0`, `on`/`off` or `yes`/`no` (from `.env` or the
published file). Anything else throws an `InvalidConfigurationException` naming the key, so a
typo never quietly becomes the default.

The other keys are just as strict. One that is not set — left out, `null` or blank (`''` or
whitespace, such as an `ADVERTISEMENTS_FALLBACK_LOCALE=` line) — takes its default; one you set to
the wrong shape throws an `InvalidConfigurationException` naming the key: the currency, locale,
bucket names, display variant, text-ad view, disk and queue settings must be strings, and every
responsive width a real width (a blank entry inside the list throws). `php artisan about` shows a
broken setting as `INVALID`.

## Usage

### The `Advertisements` facade

```php
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Money\Money;

$ad = Advertisements::create(new AdvertisementData(
    name: 'Vintage road bike',
    price: Money::ofMajor('250.00', 'EUR'),
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

The whole API at a glance:

| Call | Returns |
|---|---|
| `create(AdvertisementData)`, `update($ad, AdvertisementData)` | `Advertisement` |
| `publish($ad, ?$at)`, `unpublish($ad)`, `expire($ad, ?$at)`, `archive($ad)` | `Advertisement` |
| `delete($ad)` | `bool` |
| `query()`, `active()` | `Builder<Advertisement>` |
| `in($placement)`, `targetedIn($placement, ?$viewer)` | active ads in a placement (geo-aware) |
| `random(?$placement)` | `?Advertisement` |
| `render($ad, $placement, array $attributes = [])` | `HtmlString` — the creative or the text ad |
| `for($ad)->placements()->attach/detach/sync([...])` | `Advertisement` (placements reloaded) |
| `for($ad)->runsIn($placement)` | `bool` |
| `for($ad)->track(?$placement)->impression(?ImpressionData)` / `->click(?ImpressionData)` | `?AdvertisementEvent` (null when buffered) |

#### Without the facade

The facade is sugar over `RoundlyConsulting\Advertisements\AdvertisementManager` — inject it
and call the same methods. Every operation is also an injectable action class
(`RoundlyConsulting\Advertisements\Actions\*`) with a single `execute()` method; the manager
resolves them from the container, so a host binding for an action applies to the facade too.

```php
use RoundlyConsulting\Advertisements\Actions\PublishAdvertisement;
use RoundlyConsulting\Advertisements\AdvertisementManager;
use RoundlyConsulting\Advertisements\Models\Advertisement;

final class LaunchCampaign
{
    public function __construct(private AdvertisementManager $ads) {}

    public function __invoke(Advertisement $ad): void
    {
        $this->ads->for($ad)->placements()->sync(['sidebar', 'header']);
        $this->ads->publish($ad);
    }
}

// The raw use case:
app(PublishAdvertisement::class)->execute($ad, now()->addDay());
```

### The `AdvertisementData` DTO

Both `create` and `update` take an `AdvertisementData`. Only `name` is required; `price` is a
nullable `RoundlyConsulting\Money\Money` (a price-less ad passes `price: null` or omits it). On
create an omitted argument is simply empty; on update it means "keep the stored value" (see
[Updating an advertisement](#updating-an-advertisement)):

```php
use Illuminate\Support\Collection;
use RoundlyConsulting\Advertisements\DataTransferObjects\AdvertisementData;
use RoundlyConsulting\Money\Money;

$data = new AdvertisementData(
    name: 'Vintage road bike',
    price: Money::ofMinor(25000, 'EUR'), // 250.00 €
    category: $bikesCategory, // a Category model, an id, or a slug — resolved to category_id
    description: 'Lightly used, great condition.',
    author: $user,
    meta: new Collection(['featured' => true]),
    publishedAt: now(),
    expiresAt: now()->addMonth(),
);
```

Or use a factory that names the unit, defaulting the currency to `default_currency`. Neither
accepts a float:

```php
// Minor units (cents): an int or an integer string — wider than int64 is fine.
$data = AdvertisementData::fromMinor(name: 'Vintage road bike', minor: 25000);          // 250.00 EUR
$data = AdvertisementData::fromMinor(name: 'Bike', minor: '00199', currency: 'USD');    // 1.99 USD

// Decimal major units: exact, exponent-aware, never rounded.
$data = AdvertisementData::fromDecimal(name: 'Bike', amount: '1.10');                   // 110 cents
$data = AdvertisementData::fromDecimal(name: 'Bike', amount: '1500', currency: 'JPY');  // 1500 yen
AdvertisementData::fromDecimal(name: 'Bike', amount: '19.999');                         // throws RoundingNecessary
```

Both take the same optional `category`, `description`, `author`, `meta`, `publishedAt` and
`expiresAt` arguments as the constructor.

#### From a request

Validate with money's rules and read the price with `Request::money()` — it converts a JSON
float (`1.1`) exactly, where `(int) ceil($request->float('price') * 100)` stores 1.10 € as 1.11 €:

```php
use RoundlyConsulting\Money\Rules\{CurrencyCode, MoneyAmount};

$request->validate([
    'price'    => ['required', MoneyAmount::inCurrencyFrom('currency')->min('0')],
    'currency' => ['required', new CurrencyCode],
]);

// Preferred — JSON or form bodies:
$data = new AdvertisementData(
    name: (string) $request->string('name'),
    price: $request->money('price', currencyKey: 'currency'),
);

// Form-encoded input only (the value is already a string):
$data = AdvertisementData::fromDecimal(
    name: (string) $request->string('name'),
    amount: (string) $request->string('price'),
    currency: (string) $request->string('currency'),
);
```

In API resources, render the price with money's resource — `from()` returns `null` for a
price-less ad:

```php
use RoundlyConsulting\Money\Http\Resources\MoneyResource;

'price' => MoneyResource::from($ad->price),
// app locale `en`: {"minor":"1999","decimal":"19.99","currency":"EUR","exponent":2,"formatted":"€19.99"}
// app locale `sk`: … "formatted":"19,99 €" (a non-breaking space before €)
```

`minor` is a JSON **string**; a frontend doing arithmetic reads `decimal` (or `BigInt(minor)`).
`formatted` follows the active app locale (`app()->setLocale()`), so it changes with the request's
language — display it, never parse it.

### Updating an advertisement

```php
$ad = Advertisements::update($ad, new AdvertisementData(
    name: 'Vintage road bike (reduced)',
    price: Money::ofMinor(19900, 'EUR'),
));
```

`update()` changes only the fields you pass. `name` is always required; every argument you
**leave out** keeps the ad's stored value, so the price cut above leaves the description,
category, meta, author, placements and the `publishedAt` / `expiresAt` dates alone and a live ad
stays live. Pass an argument as an explicit `null` to **clear** it: `author: null` dissociates
the author, `category: null` uncategorises the ad, `publishedAt: null` takes it back to draft.
`fromMinor()` / `fromDecimal()` follow the same rule for their optional arguments.

A price in another currency re-denominates the ad (the action writes the `currency` column
first); `price: null` clears the amount and leaves `currency` as it was.

### Status

Every advertisement has a `status` of type `RoundlyConsulting\Advertisements\Enums\AdvertisementStatus`:

| Case | Meaning |
|---|---|
| `Draft` | Created, not published. |
| `Scheduled` | `published_at` is in the future. |
| `Published` | Live now and not expired. |
| `Expired` | `expires_at` is in the past. |
| `Archived` | Explicitly archived. |

Read the status through the `status` accessor (or the `is*()` helpers and the query scopes).
It is recomputed on every read: an ad you `publish()` or `archive()` reports its new status on
the same instance, and an ad crossing its `published_at` or `expires_at` reads as `Published` /
`Expired` the moment the clock passes it, even in a long-lived worker and without a re-save.

```php
$ad->status;          // AdvertisementStatus::Draft
$ad->publish();
$ad->status;          // AdvertisementStatus::Published — same instance, no refresh needed
$ad->isPublished();   // true
```

The `status` column holds a snapshot: `create()`, `update()` and every lifecycle action write the
status the dates give at that moment, and only `Archived` overrides the dates. Archiving sticks
through `update()` and `expire()` (an archived ad gets the expiry date but stays archived); only
`publish()` and `unpublish()` take an ad out of the archive. Time moves on without rewriting the
column, so a scheduled ad's column still says `scheduled` after it goes live.
Query by state with the scopes below (`published()`, `active()`, `expired()`, …), which compare
the dates, rather than with `where('status', …)`; `archived()` is the one that reads the column.

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

### Prices

`price` is cast with money-for-laravel's `AsMoney::currencyColumn('currency')` to a
`RoundlyConsulting\Money\Money`: the amount lives in `price` as **minor units** (a
`decimal(38,0)` column), the code in `currency`.

```php
use RoundlyConsulting\Money\Money;

$ad->price = Money::ofMajor('15.00', 'EUR');
$ad->price->minor();        // "1500" — a string, never (int)-cast it
$ad->price->toDecimal();    // "15.00"
$ad->price->format('sk');   // "15,00 €"
$ad->currency;              // "EUR"

Advertisement::query()->orderBy('price')->get();   // numeric order
```

- Only `Money|null` can be assigned; anything else throws
  `RoundlyConsulting\Money\Exceptions\InvalidMoneyValue`.
- Assigning a price in another currency to a priced ad throws `CurrencyMismatch` — set
  `currency` first to re-denominate on purpose (`UpdateAdvertisement` does this for you).
- Writing `null` clears the amount and keeps `currency`.
- pgsql and MySQL store all 38 digits; SQLite is exact only within int64, so the cast refuses
  wider amounts there (`InvalidMoneyValue`) instead of silently storing a float.
- The text-ad fallback renders `$ad->price->format()` in the active locale.

### Placements (zones)

Model where an ad renders — sidebar, header, in-feed — and serve the live ads for a zone in
one call:

```php
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Models\Placement;

$sidebar = Placement::factory()->create(['slug' => 'sidebar', 'name' => ['en' => 'Sidebar']]);

Advertisements::for($ad)->placements()->attach(['sidebar', $headerId]); // models, ids, or slugs
Advertisements::for($ad)->placements()->sync(['sidebar']);
Advertisements::for($ad)->placements()->detach(['sidebar']);
Advertisements::for($ad)->runsIn('sidebar'); // true

// Active ads in a placement, or one at random:
Advertisements::in('sidebar')->get();
Advertisements::random('sidebar');

Advertisement::query()->forPlacement($sidebar)->get(); // model, id, or slug
```

A string reference is matched as a slug first; a digit-only string that no placement uses as its
slug is then taken as an id, so form and route input (`'12'`) works as-is. Categories resolve the
same way. An unknown reference is skipped by `attach()` / `sync()` / `detach()` and matches
nothing in `in()` / `forPlacement()` / `inCategory()`.

### Impression & click tracking

Record impressions and clicks — synchronously, or buffered to the queue when
`tracking.buffered` is true. Both paths write an `advertisement_events` row and bump a
denormalized counter in one transaction (a failed write leaves neither, so a retried job never
double-counts), then fire an event:

```php
use RoundlyConsulting\Advertisements\DataTransferObjects\ImpressionData;
use RoundlyConsulting\Advertisements\Facades\Advertisements;

Advertisements::for($ad)->track('sidebar')->impression();
Advertisements::for($ad)->track('sidebar')->click(new ImpressionData(
    ip: $request->ip(),
    userAgent: $request->userAgent(),
    referrer: $request->headers->get('referer'),
));
Advertisements::for($ad)->track()->impression(); // no placement

$ad->impressions(); // int  (impressions_count)
$ad->clicks();      // int  (clicks_count)
$ad->ctr();         // float 0.0–1.0
$ad->events;        // HasMany<AdvertisementEvent>
```

`track()` refuses an ad that is not live right now — a draft, scheduled, expired or archived ad
(`AdvertisementNotActive`) — and `track($placement)` also refuses a placement the ad does not run
in (`AdvertisementNotInPlacement`). Both are `AdvertisementException`s, so a forged or stale
impression/click URL credits nothing; catch the base class in a click-redirect controller and
answer it as you see fit:

```php
use RoundlyConsulting\Advertisements\Exceptions\AdvertisementException;

try {
    Advertisements::for($ad)->track('sidebar')->click(new ImpressionData(ip: $request->ip()));
} catch (AdvertisementException) {
    // not credited: the ad is not live, or never ran in this placement
}
```

Recording returns the persisted `AdvertisementEvent`; with buffering on it returns `null` and runs
via `RecordAdvertisementEventJob` on the configured connection/queue (the live and placement
checks run when you call `track()`, before the job is queued).

Listen for `ImpressionRecorded` / `ClickRecorded` (each exposes a public `$event`
`AdvertisementEvent`).

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

`inCategory()` accepts a model, id, slug or numeric-string id; an unknown one matches nothing.

### Slugs and route binding

Slugs are generated by `sluggable-for-laravel`.

**Advertisements** carry a per-locale slug map built from each locale's `name`:

- On create, every locale present in `name` gets a slug, each transliterated in its own
  language (`['en' => 'Red Chair', 'sk' => 'Červená stolička']` → `red-chair` / `cervena-stolicka`).
- On update, only the locales whose `name` changed are regenerated; a rename that keeps the
  same base never churns `chair-2` into `chair-3`.
- Collisions are per locale and get a numeric suffix starting at `-2` (`vintage-road-bike`,
  `vintage-road-bike-2`, …); soft-deleted ads keep their slug reserved. Different locales may
  share a slug. An empty `name` generates nothing.
- A unique index per locale enforces this in the database too; a concurrent writer that loses
  the race is re-suffixed and retried.

Advertisements bind by slug: the active locale first, then `fallback_locale`, then any other
supported locale — so a link shared in another language still resolves. URLs are generated
from the current-locale slug:

```php
// routes/web.php
Route::get('/ads/{advertisement}', fn (Advertisement $advertisement) => $advertisement)->name('ads.show');
// GET /ads/vintage-road-bike resolves the ad whose slug matches, preferring the current locale.

route('ads.show', $ad);                                   // current-locale slug
Advertisement::query()->whereSlug('vintage-road-bike')->first();
Advertisement::query()->whereSlug('stary-bicykel', locale: 'sk')->first();
$ad->slugFor('sk');                                       // exact locale, no fallback
```

With `slugs.history` on, every retired slug is remembered and a GET for it answers **301** to
the current URL (query string kept). A force-deleted or pruned advertisement takes its history
rows with it; a soft-deleted one keeps them (and its slug reserved) for a restore.

**Placements and categories** have a single string slug — a code-facing key such as
`'sidebar'`. Leave it empty and it is generated from the `fallback_locale` name (`Side Banner`
→ `side-banner`, suffixed `-2` when taken). A slug you supply is kept byte-for-byte, and a taken
one throws `SlugAlreadyTakenException` instead of being silently suffixed. Once set it never
changes: a placement's slug is also the storage key of its creatives (`creative:{slug}`), so a
rename must not orphan them.

Validate admin input with sluggable's rules, which apply exactly these semantics:

```php
use RoundlyConsulting\Sluggable\Rules\UniqueSlug;
use RoundlyConsulting\Sluggable\Rules\ValidSlug;

'slug' => ['nullable', 'string', ValidSlug::for(Placement::class), UniqueSlug::for(Placement::class)->ignore($placement)],
'slug' => ['nullable', 'array', UniqueSlug::for(Advertisement::class)->ignore($ad)],   // errors on slug.{locale}
```

### Convenience methods

```php
$ad->isPublished(); $ad->isActive(); $ad->isExpired(); $ad->isScheduled(); $ad->isArchived();

$ad->publish();   // sugar for Advertisements::publish($ad) — same manager, same event, seen by the fake
$ad->unpublish(); $ad->expire(); $ad->archive(); $ad->delete();
$ad->renderCreative('sidebar'); // sugar for Advertisements::render($ad, 'sidebar')

$ad->price?->add(Money::ofMinor(500, 'EUR')); // money's API; mismatched currencies throw CurrencyMismatch
```

### Pruning expired advertisements

The model is `Prunable`; advertisements whose `expires_at` is in the past are pruned:

```bash
php artisan model:prune --model="RoundlyConsulting\Advertisements\Models\Advertisement"
```

Each expired ad is force-deleted one by one, so pruning cleans up after it: its creatives (media
rows and files), placement links, tracking events and slug history go with it, and
`AdvertisementDeleted` fires for it like for any other delete. A plain `$ad->delete()` stays a
soft delete that keeps the creatives for a restore; `$ad->forceDelete()` removes them too.

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

`Advertisements::fake()` swaps in `RoundlyConsulting\Advertisements\Testing\AdvertisementsFake`
— a subtype of `AdvertisementManager`, installed behind the facade **and** in the container, so
constructor-injected managers and the model methods (`$ad->publish()`, `$ad->delete()`, …) hit it
too. Lifecycle and placement changes still run against the database (reads and events behave
normally) and are recorded; tracking is recorded in memory only — no row, no counter, no job — and
returns `null`. The live-ad and placement checks of `track()` still apply.

```php
use RoundlyConsulting\Advertisements\Facades\Advertisements;
use RoundlyConsulting\Advertisements\Testing\RecordedEvent;
use RoundlyConsulting\Advertisements\Testing\RecordedPlacements;

$fake = Advertisements::fake();

$ad->publish();
Advertisements::for($ad)->placements()->attach(['sidebar']);
Advertisements::for($ad)->track('sidebar')->impression();

$fake->assertImpressionRecorded(fn (RecordedEvent $e) => $e->ad->is($ad) && $e->placement === 'sidebar');
$fake->assertPublished($ad);
$fake->assertPlacementsAttached(fn (RecordedPlacements $c) => $c->includes('sidebar'));
$fake->assertNothingDeleted();
```

| Recorded | Assert | Negative |
|---|---|---|
| `create` | `assertCreated(Advertisement\|Closure\|null)` | `assertNothingCreated()` |
| `update` | `assertUpdated(…)` | `assertNothingUpdated()` |
| `publish` / `$ad->publish()` | `assertPublished(…)` | `assertNothingPublished()` |
| `unpublish` / `$ad->unpublish()` | `assertUnpublished(…)` | `assertNothingUnpublished()` |
| `expire` / `$ad->expire()` | `assertExpired(…)` | `assertNothingExpired()` |
| `archive` / `$ad->archive()` | `assertArchived(…)` | `assertNothingArchived()` |
| `delete` / `$ad->delete()` | `assertDeleted(…)` | `assertNothingDeleted()` |
| `for($ad)->placements()->attach()` | `assertPlacementsAttached(Advertisement\|Closure(RecordedPlacements)\|null)` | `assertNothingAttached()` |
| `for($ad)->placements()->detach()` | `assertPlacementsDetached(…)` | `assertNothingDetached()` |
| `for($ad)->placements()->sync()` | `assertPlacementsSynced(…)` | `assertNothingSynced()` |
| `for($ad)->track()->impression()` | `assertImpressionRecorded(Advertisement\|Closure(RecordedEvent)\|null)` | `assertNothingRecorded()` |
| `for($ad)->track()->click()` | `assertClickRecorded(…)` | `assertNothingRecorded()` |

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
echo Advertisements::render($ad, 'sidebar', ['class' => 'ad']);
echo $ad->renderCreative('sidebar', ['class' => 'ad']); // the same, from the model
```

The text ad renders through the publishable `advertisements::text-ad` Blade view, so hosts can
restyle it. It applies every attribute you pass to its `<div>`, escaped: `class` and `style` are
appended to its own (`class="advertisement-text-ad ad"`), anything else is added or overrides. The
image path hands them to media-library's responsive `<img>`, which takes `class`, `alt` (defaulting
to the ad name) and `sizes`. Bind your own `RoundlyConsulting\Advertisements\Contracts\CreativeRenderer` to swap
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
$ads = Advertisements::targetedIn('sidebar', request())->get();

// Or the model scope directly:
Advertisement::query()->active()->forPlacement('sidebar')->targetedAt($location)->get();
```

An ad matches when the viewer satisfies **every** rule it sets: the allow/deny lists check the
viewer's country, the radius checks the viewer's coordinates. Ads with no targeting (or an
empty `new Targeting()`, which is stored as no targeting) match every viewer while
`geo.untargeted_match` is on.

The viewer can be a `Location`, bare `Coordinates`, an ISO country code, a `Request` (resolved
from its IP) or `null`. What happens when something about the viewer is unknown is decided by
`geo.match_when_unknown`:

| The viewer is… | `untargeted_only` (default) | `all` |
|---|---|---|
| unknown — `null`, an IP geolocation cannot place (or whose lookup throws), an empty country code, a `Location` with neither country nor coordinates | only untargeted ads | every ad |
| known only by country (`'US'`, a `Location` at 0,0) | a radius rule fails | a radius rule passes |
| known only by coordinates (`Coordinates`, a `Location` without a country) | a country list fails | a country list passes |

So under the default a viewer known only as `'US'` is never served an ad restricted to 50 km
around Bratislava, and bare New York coordinates never get an `SK`-only ad.

### Geo-reported impressions

When `geo.stamp_events` is on, recording stamps the viewer's country (indexed `country_code`
column) plus region/city/coords (in the event `meta`) from the impression IP, for per-country
reporting:

```php
use RoundlyConsulting\Advertisements\DataTransferObjects\ImpressionData;

Advertisements::for($ad)->track('sidebar')->impression(new ImpressionData(ip: $request->ip()));

$ad->impressionsByCountry(); // ['SK' => 1240, 'CZ' => 310]
$ad->clicksByCountry();      // ['SK' => 58]
```

Resolution failures never abort the record: an IP geolocation cannot place, a viewer it places
without a country, and a lookup that throws (a missing MaxMind database, a rate limit, a custom
provider's exception) all save the event with a null `country_code` and still bump the counter.
A throwing lookup is passed to your exception handler's `report()`, so a broken provider shows
up in your logs instead of failing silently; serving (`targetedIn()`) treats it as an unknown
viewer the same way.

## Integrates with

This package builds directly on four sibling roundly-consulting packages, installed
automatically as dependencies:

- **[media-library-for-laravel](https://github.com/roundly-consulting/media-library-for-laravel)**
  — per-placement creatives, responsive variants, the `display` variant, and the text-ad fallback.
- **[geolocation-for-laravel](https://github.com/roundly-consulting/geolocation-for-laravel)**
  — viewer location for geo-targeting and the geo-stamped, per-country impression reports.
- **[sluggable-for-laravel](https://github.com/roundly-consulting/sluggable-for-laravel)**
  — per-locale advertisement slugs, strict category/placement keys, database-enforced
  uniqueness, any-locale route binding, slug history with 301 redirects, and validation rules.
- **[money-for-laravel](https://github.com/roundly-consulting/money-for-laravel)**
  — the `Money` price, the `AsMoney` cast and `$table->money()` column, the exact
  `fromMinor()` / `fromDecimal()` factories, and the `MoneyAmount` / `Request::money()` /
  `MoneyResource` host recipe.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=advertisements-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=advertisements-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
