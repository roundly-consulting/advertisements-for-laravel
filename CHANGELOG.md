# Changelog

All notable changes to `advertisements-for-laravel` will be documented in this file.

## Unreleased

### Added

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
