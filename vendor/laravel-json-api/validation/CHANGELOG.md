# Change Log

All notable changes to this project will be documented in this file. This project adheres to
[Semantic Versioning](http://semver.org/) and [this changelog format](http://keepachangelog.com/).

## Unreleased

## [4.3.0] - 2025-02-24

### Added

- Package now supports Laravel 11 and 12.

## [4.2.0] - 2024-11-30

### Added

- Package now requires `laravel-json-api/core` v4 or v5.

### Fixed

- Remove deprecation notices in PHP 8.4.

## [4.1.1] - 2024-06-20

### Fixed

- [laravel#287](https://github.com/laravel-json-api/laravel/issues/287) Use `array_shift` to avoid potential problem
  introduced in Laravel.

## [4.1.0] - 2024-03-23

### Added

- New rule objects:
    - `JsonArray` - ensures a value is a zero-indexed array, i.e. it would have been an array in JSON.
    - `JsonObject` - ensures a value is an associative array, i.e. it would have been an object in JSON.

## [4.0.0] - 2024-03-12

### Changed

- Package is now licensed under the [MIT License.](./LICENSE)
- **BREAKING** Package now requires Laravel 11.
- Minimum PHP version is now `8.2`.

## [3.0.1] - 2024-06-20

### Fixed

- [laravel#287](https://github.com/laravel-json-api/laravel/issues/287) Use `array_shift` to avoid potential problem
  introduced in Laravel.

## [3.0.0] - 2023-02-14

### Changed

- Upgraded to Laravel 10 and set minimum PHP version to `8.1`.

## [2.1.3] - 2023-02-09

### Fixed

- The following rules all previously errored if a non-array value was provided. This is now fixed:
    - `AllowedFieldSets`
    - `AllowedPageParameters`
    - `AllowedFilterParameters`
- The `AllowedCountableFields` rule now correctly passes an empty string.

## [2.1.2] - 2023-01-25

### Fixed

- [laravel#225](https://github.com/laravel-json-api/laravel/issues/225) Fix `AllowedFieldSets` validation of an empty
  value (empty string or `null`) for a resource type's fields.

## [2.1.1] - 2023-01-15

### Fixed

- Removed unused dynamic properties from two rule classes. These were causing deprecation failures in PHP 8.2.

## [2.1.0] - 2022-04-11

### Added

- [#8](https://github.com/laravel-json-api/validation/pull/8) Add Spanish and Brazilian Portuguese translations.

## [2.0.0] - 2022-02-09

### Changed

- Package now supports Laravel 9.
- Added return types to internal methods to remove deprecation messages in PHP 8.1.
- Minimum `laravel-json-api/core` dependency version is now `2.0`.

## [1.2.0] - 2022-02-04

### Added

- [#7](https://github.com/laravel-json-api/validation/pull/7) Add Italian translation files.

## [1.1.0] - 2022-01-22

### Added

- New validation rules:
    - `JsonNumber` validates that the value is a number in JSON (i.e. integer or float), or just an integer.
    - `JsonBoolean` validates that the value is a boolean in JSON. It can also be used for query string parameters that
      will be filtered to booleans via its `asString()` method.

## [1.0.0] - 2021-07-31

Initial stable release, with no changes from `1.0.0-beta.3`.

## [1.0.0-beta.3] - 2021-07-10

### Fixed

- [#4](https://github.com/laravel-json-api/validation/issues/4) Ensure JSON pointer does not have a double slash when
  using the `withSourcePrefix()` method on the `ValidatorErrorIterator` class.
- [#3](https://github.com/laravel-json-api/validation/pull/3) Add missing return statement to the
  `ValidatorErrorIterator::withSourcePrefix()` method.

## [1.0.0-beta.2] - 2021-04-26

### Changed

- Updated the `AllowedSortParameters` rule class to use renamed method on the `Schema` contract.

## [1.0.0-beta.1] - 2021-03-30

### Added

- New `AllowedCountableFields` rule, which can be created via the `Rule::countable` and `Rule::countableForPolymorph`
  methods.

## [1.0.0-alpha.5] - 2021-03-12

### Added

- New `Rule::includePathsForPolymorph()` method. This returns an include path rule that is configured to allow all the
  include paths for every inverse schema that a polymorphic relation has.

### Fixed

- The correct message is now set as the error detail when a query parameter is not supported. Previously the translation
  key was displayed instead of the translated message.

## [1.0.0-alpha.4] - 2021-02-27

### Added

- The sparse field sets rule now rejects with a specific message indicating if any of the supplied resource types in the
  `fields` query parameter are not recognised.

## [1.0.0-alpha.3] - 2021-02-09

### Added

- Added French translations for error messages.

## [1.0.0-alpha.2] - 2021-02-02

### Bugfix

- [#1](https://github.com/laravel-json-api/validation/issues/1)
  Added package discovery configuration to the `composer.json`.

## [1.0.0-alpha.1] - 2021-01-25

Initial release.
