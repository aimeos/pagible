# Change Log

All notable changes to this project will be documented in this file. This project adheres to
[Semantic Versioning](http://semver.org/) and [this changelog format](http://keepachangelog.com/).

## Unreleased

## [4.2.0] - 2025-02-24

### Added

- Package now supports Laravel 11 and 12.

## [4.1.0] - 2024-11-30

### Added

- Allow `laravel-json-api/core` v4 or v5.

### Fixed

- Remove deprecation notices in PHP 8.4.

## [4.0.0] - 2024-03-14

### Changed

- Package is now licensed under the MIT License.
- **BREAKING** Package now requires Laravel 11.
- Minimum PHP version is now `8.2`.

## [3.1.1] - 2023-11-20

### Fixed

- [#5](https://github.com/laravel-json-api/encoder-neomerx/issues/5) Fix error with default value for `$jsonApi`
  property on the `Document` class.

## [3.1.0] - 2023-11-08

### Changed

- Encoding implementation no longer catches and re-throws exceptions when encoding compound document. Although the
  implementation here was correct, we're getting far too many questions from developers who do not refer to the previous
  exception even though the exception message specified that there was a previous exception.

## [3.0.1] - 2023-02-18

### Changed

- Update exceptions thrown when the encoding process fails to notify developer to check previous exception for the cause
  of the failure.

## [3.0.0] - 2023-02-14

### Changed

- Upgraded to Laravel 10 and set minimum PHP version to `8.1`.

## [2.0.1] - 2022-06-25

### Fixed

- Upgraded the `laravel-json-api/neomerx-json-api` dependency to `^5.0.1`. This fixes a bug related to the top-level
  meta member. Previously the encoder added empty meta values to the top-level member, which is incorrect.

## [2.0.0] - 2022-02-09

### Added

- Package now supports Laravel 9.
- Package now supports PHP 8.1.

### Changed

- Upgraded the `laravel-json-api/neomerx-json-api` dependency to `5.0`.
- Upgraded the `laravel-json-api/core` dependency to `2.0`.
- Added return types to internal methods to remove deprecation messages in PHP 8.1

## [1.1.0] - 2022-01-03

### Fixed

- Fixed setting the top-level `jsonapi` value on the `Document` class, which was not setting the cast value.

### Removed

- The `RelationshipDocument` no longer merges relationship links with the top-level document links. This is because we
  now expect the top-level links provided to the encoder to already have the relationship links merged. The `core`
  package takes care of this in the relationship response classes, while also providing the capability for the developer
  to turn off link merging if desired (which is a better implementation). This change is considered non-breaking because
  the core package dependency has been upgraded and there were existing bugs in the links merging implementation within
  the `RelationshipDocument` class. I.e. it would fail if either of the self or related links were missing, or if the
  relationship was hidden - so removing this merging fixes bugs in the implementation.

## [1.0.0] - 2021-07-31

Initial stable release, with no changes since `1.0.0-beta.1`.

## [1.0.0-beta.1] - 2021-03-30

### Changed

- Updated the encoder to implement changes made to the encoder interface. This removes the `withIdentifiers()` method
  and replaces it with the `withToOne()` and `withToMany()` methods.

### Fixed

- When encoding relationships, do not yield a relationship value that is empty (no data, links or meta).

## [1.0.0-alpha.4] - 2021-02-27

### Changed

- This package now relies on `laravel-json-api/neomerx-json-api`, a fork of `neomerx/json-api`. This was required
  because of a [bug affecting empty to-many include paths.](https://github.com/laravel-json-api/laravel/issues/11)
  Although a PR was sent to the Neomerx repository, there has been no activity in that repository for almost a year. The
  fork will be maintained for use in Laravel JSON:API, but we will switch back to using the Neomerx package if it is
  maintained in the future.

### Fixed

- Conditional field values are now correctly handled when iterating over a resource's relationships.

## [1.0.0-alpha.3] - 2021-02-09

### Added

- Encoder now supports conditional fields being used in the JSON:API resource relationships.

### Fixed

- Fixed the schema's `getSelfLink()` method, which was not passing the request through to the JSON:API resource object.

## [1.0.0-alpha.2] - 2021-02-02

### Added

- [#2](https://github.com/laravel-json-api/encoder-neomerx/pull/2)
  New `withRequest` method on the encoder, allows a request to be passed into the encoding process. This is then
  provided to each `JsonApiResource` object when it is being encoded. This change was made to bring the resource into
  line with Laravel's Eloquent resource, which is passed the request when serializing models. *(Changes to classes in
  the `Schema` namespace to implement this are considered non-breaking, as they are internal classes.)*
- Resource meta can now contain conditional attributes, as the conditional iterator is used when processing meta
  returned by the `JsonApiResource` class.

### Fixed

- [#3](https://github.com/laravel-json-api/encoder-neomerx/issues/3)
  Add missing package discovery configuration to `composer.json`.

## [1.0.0-alpha.1] - 2021-01-25

Initial release.
