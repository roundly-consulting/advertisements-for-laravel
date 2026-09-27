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
- Placements (zones): attach, sync and detach ads, then serve with `Advertisements::for()` or
  `Advertisements::random()`.
- Impression and click tracking, synchronous or queued, with denormalised counters and `ctr()`.
- Per-locale translatable name, description and slug, a nestable `Category` tree with
  `inCategory()`, and slug route binding with optional 301s from retired slugs.
- Visual creatives per placement with a responsive image and a Blade text-ad fallback, built on
  media-library-for-laravel.
- Geo-targeting by country or radius (`targetedFor()`, `targetedAt()`) and per-country impression
  and click reports, built on geolocation-for-laravel.
- Model factory states and `Advertisements::fake()` with impression and click assertions.
