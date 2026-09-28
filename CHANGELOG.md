# Changelog

All notable changes to `advertisements-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- `Advertisement` model with a polymorphic author, soft deletes, JSON metadata and prunable
  expiry (`model:prune`).
- Full lifecycle through the `Advertisements` facade — `create`, `update`, `publish` (now or
  scheduled), `unpublish`, `expire`, `archive`, `delete` — each dispatching its own event.
- `AdvertisementData` DTO and status scopes: `published()`, `active()`, `scheduled()`,
  `expired()`, `draft()`, `archived()` and `forAuthor()`.
- Exact `Money` prices in minor units (`decimal(38,0)`), built on money-for-laravel.
- Placements (zones): `Advertisements::for($ad)->placements()->attach/detach/sync([...])` and
  `for($ad)->runsIn($placement)`, then serve with `Advertisements::in($placement)` or
  `Advertisements::random()`.
- Impression and click tracking, synchronous or queued, with denormalised counters and `ctr()`:
  `Advertisements::for($ad)->track($placement)->impression()/click()` returns the recorded
  `AdvertisementEvent`, or `null` when buffered. A placement the ad does not run in is refused
  (`AdvertisementNotInPlacement`).
- Per-locale translatable name, description and slug, a nestable `Category` tree with
  `inCategory()`, and slug route binding with optional 301s from retired slugs.
- Visual creatives per placement with a responsive image and a Blade text-ad fallback, built on
  media-library-for-laravel; `Advertisements::render($ad, $placement, $attributes)` (and
  `$ad->renderCreative()`) render them.
- Geo-targeting by country or radius (`Advertisements::targetedIn()`, `targetedAt()`) and per-country impression
  and click reports, built on geolocation-for-laravel.
- Model factory states and `Advertisements::fake()`: `AdvertisementsFake` extends
  `AdvertisementManager`, so injected managers and model methods (`$ad->publish()`, …) hit it; it
  records lifecycle (`assertCreated/Updated/Published/Unpublished/Expired/Archived/Deleted` +
  `assertNothing*`), placement changes (`assertPlacementsAttached/Detached/Synced` +
  `assertNothingAttached/Detached/Synced`, `RecordedPlacements::includes()`) and tracking
  (`assertImpressionRecorded/ClickRecorded/NothingRecorded`).

### Changed

- `AdvertisementManager` is no longer `final`, takes the container, and resolves every action
  from it (a host binding for an action applies to the facade).
- `Advertisements::for($placement)` → `Advertisements::in($placement)`; `targetedFor()` →
  `targetedIn()`; `for()` now takes an `Advertisement` and returns its scoped handle.
- `attachPlacements/detachPlacements/syncPlacements($ad, …)` → `for($ad)->placements()->…`;
  `recordImpression/recordClick($ad, $placement, $data)` → `for($ad)->track($placement)->…`.
- `RecordImpression` / `RecordClick` (and tracking) return `?AdvertisementEvent` instead of
  `AdvertisementEvent|PendingDispatch`; the buffered path dispatches the job and returns `null`.
- `$ad->publish()/unpublish()/expire()/archive()/delete()` and `renderCreative()` delegate to the
  manager instead of calling actions, so the fake sees them.
- `RecordAdvertisementEvent`, `EventRecorder` and `Advertisement::performModelDelete()` are
  `@internal`; `ViewerLocationResolver` is bound `scoped`.
- Tests use `Geolocation::fake()` (geolocation-for-laravel's facade fake).

### Fixed

- `Advertisements::fake()` bound a non-subtype of the `final` manager, so any class
  constructor-injecting `AdvertisementManager` hit a `TypeError` under the fake.
- The fake's `recordImpression/recordClick` returned `RecordedEvent`, contradicting the facade's
  documented return type.
