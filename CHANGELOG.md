# Changelog

All notable changes to `advertisements-for-laravel` will be documented in this file.

## Unreleased

### Added

- **Placements / zones.** `Placement` model with a many-to-many relation to
  advertisements, `forPlacement()` scope, `Advertisements::for($placement)`, and
  `attachPlacements()` / `detachPlacements()` / `syncPlacements()` (accepting models, ids,
  or slugs). New `placement_model` config key.
- **Impression & click tracking.** `AdvertisementEvent` model, `AdvertisementEventType`
  enum, `ImpressionData` DTO, `RecordImpression` / `RecordClick` actions sharing a
  `RecordAdvertisementEvent` core, denormalized `impressions_count` / `clicks_count`
  counters, and `impressions()` / `clicks()` / `ctr()` helpers. Recording is synchronous
  by default or buffered to the queue via the new `tracking` config block (`buffered`,
  `queue`, `connection`), dispatched through `RecordAdvertisementEventJob`. Fires
  `ImpressionRecorded` / `ClickRecorded`. New `event_model` config key.
- **Native translatable fields.** `name`, `description`, and `slug` are per-locale JSON
  maps via the in-package `Concerns\HasTranslations` trait (no third-party dependency),
  with `getTranslation()` / `setTranslation()` / `getTranslations()` / `setTranslations()`
  / `forgetTranslation()` / `hasTranslation()`. New `fallback_locale` config key.
- **Categories.** Nestable `Category` model (parent/children/`descendants()`), `category()`
  relation on advertisements, and an `inCategory($category, $includeDescendants)` scope.
  New `category_model` config key.
- **Convenience methods.** `isPublished()` / `isActive()` / `isExpired()` / `isScheduled()`
  / `isArchived()` booleans, fluent `publish()` / `unpublish()` / `expire()` / `archive()`
  / `delete()` on the model, `Money::add()` / `subtract()` / `isZero()`, and facade
  `active()` / `random()` shortcuts.
- **Locale-aware slug route binding.** Advertisements bind by `slug` (`getRouteKeyName()`),
  matching the active locale's slug then the fallback locale's.
- **Testing helpers.** `Advertisements::fake()` returns an `AdvertisementsFake` recording
  impressions/clicks in memory with `assertImpressionRecorded()` / `assertClickRecorded()`
  / `assertNothingRecorded()`, forwarding non-tracking calls to the real manager.
- `Advertisements` facade and `AdvertisementManager` for a fluent, discoverable API.
- `AdvertisementData` DTO replacing the positional action arguments, with a
  `fromAmount()` helper backed by the new `default_currency` config key.
- First-class status via the `AdvertisementStatus` enum (draft, scheduled, published,
  expired, archived), stored in an indexed `status` column and overlaid with time-based
  expiry by the model's `status` accessor.
- Lifecycle actions and matching events: publish, unpublish, expire, archive, delete.
- Query scopes: `published`, `active`, `scheduled`, `expired`, `draft`, `archived`,
  `forAuthor`.
- `register_facade_alias` config key to opt out of the global facade alias.
- Package exceptions: `AdvertisementException` base and `InvalidPrice`.

### Changed

- Moved the model to `RoundlyConsulting\Advertisements\Models\Advertisement`.
- `CreateAdvertisement` and `UpdateAdvertisement` now take an `AdvertisementData` DTO
  (with `price` as a `Money` value object) instead of positional arguments.
- `MoneyCast` throws `InvalidPrice` instead of a bare `InvalidArgumentException`.
- **Schema (pre-1.0, single baseline):** `name`, `description`, and `slug` are now `json`
  translation maps instead of `string`/`text`; the free-text `category` string column is
  replaced by a nullable `category_id` foreign key to `categories`; and
  `impressions_count` / `clicks_count` counters are added to `advertisements`.
- `AdvertisementData::$category` (and `fromAmount()`) now accept `Category|int|string|null`
  instead of `?string`.
