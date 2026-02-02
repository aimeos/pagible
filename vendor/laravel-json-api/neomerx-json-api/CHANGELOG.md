# Change Log

All notable changes to this project will be documented in this file. This project adheres to
[Semantic Versioning](http://semver.org/) and [this changelog format](http://keepachangelog.com/).

## Unreleased

## [5.0.3] - 2024-11-29

### Fixed

- Removed deprecation messages in PHP 8.4.

## [5.0.2] - 2023-05-29

### Fixed

- [#5](https://github.com/laravel-json-api/neomerx-json-api/issues/5) Fix property used before instantiation bug in
  error class.

## [5.0.1] - 2022-06-25

### Fixed

- [#3](https://github.com/laravel-json-api/neomerx-json-api/issues/3) The encoder now omits the top-level `meta` member
  if the meta value is set to `null`. Previously it was encoding `null` into the JSON:API document, which did not
  conform to the interface which says that: "If 'null' meta will not appear in a document."
- [#3](https://github.com/laravel-json-api/neomerx-json-api/issues/3) The encoder now omits the top-level `meta` member
  if the meta value is an empty array. Previously it was encoding an empty array as the JSON value. This does not
  confirm to the JSON:API specification, which states that the `meta` member must be an object.

## [5.0.0] - 2022-02-09

### Added

- Added support for PHP 8.1.

### Changed

- **BREAKING**: All classes now have type-hinted properties.
- **BREAKING**: added return types for internal methods to remove deprecation messages for PHP 8.1.
- Minimum PHP version is now 7.4.

## [4.0.2] - 2021-02-27

**This release was forked from [neomerx/json-api](https://github.com/neomerx/json-api) at `v4.0.1`.**

### Fixed

- [#252](https://github.com/neomerx/json-api/issues/252) Fixed invoking relationship generator more than once when
  relationship is an include path, and is an empty generator.
