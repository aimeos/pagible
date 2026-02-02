# Change Log

All notable changes to this project will be documented in this file. This project adheres to
[Semantic Versioning](http://semver.org/) and [this changelog format](http://keepachangelog.com/).

## Unreleased

## [3.2.0] - 2025-02-24

### Added

- Package now supports Laravel 11 and 12.

## [3.1.0] - 2024-11-30

### Added

- Package now requires `laravel-json-api/core` v4 or v5.

### Fixed

- Removed deprecation notices in PHP 8.4.

## [3.0.0] - 2024-03-14

### Changed

- Package is now licensed under the MIT license.
- **BREAKING** Package now requires Laravel 11.
- Minimum PHP version is now `8.2`.

## [2.0.1] - 2023-10-29

### Fixed

- [#7](https://github.com/laravel-json-api/spec/pull/7) Fix translation keys in the PT-BR translation file.

## [2.0.0] - 2023-02-14

### Changed

- Upgraded to Laravel 10 and set minimum PHP version to `8.1`.

## [1.2.0] - 2022-04-10

### Added

- [#6](https://github.com/laravel-json-api/spec/pull/6) Added Spanish and Brazilian Portuguese translations.

## [1.1.1] - 2022-04-01

### Fixed

- The package now accepts `"0"` as a valid client generated id. Previously it was rejected with a message that the id
  could not be empty.
- The package now accepts a document for a resource with an expected id of `"0"`. Previously the package did not 
  recognise zero as an expected id.

## [1.1.0] - 2022-02-09

### Added

- Package now supports Laravel 9.
- Package now supports `2.0` of the `laravel-json-api/core` dependency.
- [#5](https://github.com/laravel-json-api/spec/pull/5) Add Italian translation files.

## [1.0.0] - 2021-07-31

Initial stable release, with no changes from `1.0.0-beta.2`.

## [1.0.0-beta.2] - 2021-07-10

### Changed

- **BREAKING** JSON decoding has been moved to a `JsonDecoder` class. This now throws a `JsonApiException` with
  translated error objects. Previously it threw the `UnexpectedDocumentException` class.
- **BREAKING** Document builder classes now expect the `JsonDecoder` as their first constructor argument. This is
  unlikely to affect consuming applications as the builder classes should be created via the service container.

### Removed

- **BREAKING** Removed the `UnexpectedDocumentException` class as it is no longer in use.

## [1.0.0-beta.1] - 2021-03-30

Initial beta release, no changes since `alpha.4`.

## [1.0.0-alpha.4] - 2021-02-27

### Added

- Decoding JSON document now rejects an empty string with a specific exception message indicating that JSON is expected.
- Sending an incorrect resource type for a relationship is now rejected. For example, if a relation expects `tags` but
  the client provides `posts`, it will be rejected with a message that `posts` are not supported.

### Changed

- **BREAKING** The `ToOne`, `ToMany` and `Identifier` value objects now expect the relation object to be passed into
  their constructor. This also affects the methods on the factory to create them.

## [1.0.0-alpha.3] - 2021-02-09

### Added

- Added French translations for error messages.
- If a *to-many* relation is sent for a *to-one* field (and vice versa), the value will be rejected with a new message
  that clearly indicates that the relation type is invalid.

### Changed

- **BREAKING** The constructor for the to-many and to-one values now expect the relation name to be provided. This also
  means the factory create methods for these values has been updated to expect the relation name.

## [1.0.0-alpha.2] - 2021-02-02

### Bugfix

- [#1](https://github.com/laravel-json-api/spec/issues/1) Add missing package discovery configuration to
  the `composer.json`.

## [1.0.0-alpha.1] - 2021-01-25

Initial release.
