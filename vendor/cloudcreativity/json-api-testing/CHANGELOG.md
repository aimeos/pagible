# Change Log

All notable changes to this project will be documented in this file. This project adheres to
[Semantic Versioning](http://semver.org/) and [this changelog format](http://keepachangelog.com/).

## Unreleased

## [6.2.0] - 2025-04-09

### Added

- Package now supports PHPUnit 12.

## [6.1.0] - 2025-02-24

### Added

- Package now requires `illuminate/support` 11 or 12.

## [6.0.1] - 2024-11-30

### Fixed

- Remove deprecation notices from PHP 8.4.

## [6.0.0] - 2024-03-12

### Changed

- Package is now licensed under the [MIT License.](./LICENSE)
- **BREAKING** Package now requires `illuminate/support` 10 or 11, dropping support for 9.
- **BREAKING** Package now requires PHPUnit 10 or 11, dropping support for 9.
- Minimum PHP version is now `8.2`.

## [5.0.0] - 2023-02-18

### Changed

- Package now supports PHPUnit 9 and 10 - this adds PHPUnit 10 and drops support for PHPUnit 8.
- Minimum PHP version is now 8.1 - dropping support for PHP 7.4 and 8.0.
- Drop support for Laravel 8.

## [4.1.0] - 2023-02-14

### Added

- Package now supports Laravel 10 and PHP 8.2.

## [4.0.0] - 2022-02-08

### Added

- Package now supports PHP 8.1.
- Package now supports Laravel 9.
- The package now correctly JSON encodes then decodes the expected values for assertions. This means an expected value
  can now contain JSON serializable objects, which improves the developer experience. For example, when using `Carbon`
  dates, the developer previously had to manually call `$date->jsonSerialize()` to put the expected JSON string value
  in their expected resource arrays. This also fixes a bug where the assertions failed to correctly compare floats that
  encoded to integers in JSON - e.g. `4.0` encodes as `4` but the assertion failed as it was attempting to compare a
  float to a decoded integer.

### Changed

- Added return types to internal methods to remove deprecation messages in PHP 8.1.
- Added property type hints to all classes and amended method type-hints where these needed updating.
- The `assertStatusCode` method now expects the status code to be an integer. Previously it allowed
  strings.
- The `assertIncluded` method type-hint for the expected value has changed from `array` to `iterable`.
- Renamed the `IdentifiersInDocument` constraint `IdentifiersInOrder`. In addition, this now extends the 
  `SubsetsInOrder` constraint, rather than the `SubsetInDocument` constraint.
- The `HasHttpAssertions` trait now does not throw an exception for its `getExpectedType()` method if the expected 
  string is empty. Instead an exception is thrown from the `JsonObject` method that casts an id value to a resource
  identifier if the expected type is empty. This is an improvement because it means an expected type only needs to be
  set if you are using a `UrlRoutable`, `int` or `string` value for an assertion. Previously an exception would be
  thrown stating that an expected type needed to be set even if the expected type did not need to be used, e.g. if using
  an array value that had the `type` key set.

### Removed

- Removed the `Assert::assertExactListInOrder` assertion. Use `Assert::assertExact` instead.
- Removed the `HasDocumentAssertions::assertExactListInOrder` assertion, which means it is also removed from the
  `Document` class. Use `assertExact` instead.
- Removed the following methods from the `Compare` class and the `HasHttpAssertions` trait. The `Utils\JsonObject` and
  `Utils\JsonStack` classes should be used instead:
  - `identifiers()`
  - `identifier()`
  - `identifiable()`
- Removed the following deprecated methods:
  - `assertDeleted()` - use `assertNoContent()` or `assertMetaWithoutData()` depending on your expected response.
  - `assertUpdated()` - use `assertNoContent()` or `assertFetchedOne()` depending on your expected response.
- The `HttpMessage` class previously delegated methods calls to the `Document` class if the method did not exist on the
  message. This was not actually in use and unnecessarily increased the complexity of the messsage class. It has 
  therefore been removed. Call methods directly on the document if needed.  

## [3.5.0] - 2022-01-22

### Added

- [#18](https://github.com/cloudcreativity/json-api-testing/issues/18) The `assertMetaWithoutData` and
  `assertExactMetaWithoutData` assertions now assert a successful HTTP status code. Previously they were expecting
  `200 OK` though this is too restrictive for a meta-only response. However, the assertions will continue to fail for
  `204 No Content` responses because they are expecting the response to have content.
- [#14](https://github.com/cloudcreativity/json-api-testing/issues/14) The expected `Location` header passed to the
  `assertCreatedWithClientId` assertion can now include the expected resource id. Previously the expected header value
  had to be passed without the id.

## [3.4.0] - 2022-01-16

### Added

- The `assertCreatedWithServerId`, `assertCreatedWithClientId` and `assertCreatedNoContent` methods will now fail with a
  better assertion message if the Location header is missing.
- New `assertDoesntHaveIncluded` assertion to assert that the JSON:API document does not have the top-level `included`
  member.
- New `assertDoesntHaveMeta` assertion to assert the JSON:API document does not have the top-level `meta` member.
- New `assertDoesntHaveLinks` assertion to assert the JSON:API document does not have the top-level `links` member.

### Fixed

- The `assertFetchedManyInOrder` assertion did not work if the expected `data` was an empty array.
- The `assertFetchedToMany` and `assertFetchedToManyInOrder` assertions did not work if the expected `data` was an empty
  array.

## [3.3.0] - 2021-07-31

### Changed

- Minimum PHP version is now `7.4`.
- Minimum Laravel version is now `8.0`.
- Minimum PHPUnit version is now `9.0`.

### Fixed

- [#19](https://github.com/cloudcreativity/json-api-testing/issues/19) The expected location for the
  `assertCreatedWithServerId()` and `assertCreatedWithClientId()` can now be `null`, indicating that the `Location`
  header is not expected.

### Deprecated

- The following methods are deprecated and will be removed in `4.0`:
  - `assertDeleted()` - use `assertNoContent()` or `assertMetaWithoutData()` depending on your expected response.
  - `assertUpdated()` - use `assertNoContent()` or `assertFetchedOne()` depending on your expected response.

## [3.2.0] - 2020-11-25

### Added

- Package now supports PHP 8.

## [3.1.0] - 2020-09-09

### Added

- Package now supports Laravel 8.

## [3.0.0] - 2020-04-12

### Added

- Package now supports PHP Unit 9.

### Changed

- Dropped support for PHP Unit 7.
- Dropped support for Laravel 5.

## [2.1.0] - 2020-03-04

### Added

- Package now supports Laravel 7.
- [#9](https://github.com/cloudcreativity/json-api-testing/issues/9)
  Can now assert that there are no resources included - i.e. that the `included` member is not present or is an empty
  list.

### Fixed

- [#10](https://github.com/cloudcreativity/json-api-testing/pull/10)
  Pass expected and actual values to PHPUnit's assertion in correct order.

## [2.0.0] - 2019-10-14

### Changed

- Minimum `illuminate/support` version is now `5.8` (previously `5.5`).
- Minimum PHPUnit version is now `7.5` (previously `6.0`).

### Removed

- Removed the `assertNoContent()` method from the `Concerns\HasHttpAssertions` trait. Unlike other assertion methods in
  that trait, this method refers to a HTTP status description which means it is likely to collide with assertions
  provided by frameworks. For example, Laravel `6.1.0` introduced an `assertNoContent()` method to its test response
  which is not compatible with the implementation provided by this package.

## [1.2.0] - 2019-09-04

### Added

- Package now supports Laravel 6.

## [1.1.0] - 2019-05-23

### Added

- Package now supports PHPUnit 8.

## [1.0.0] - 2019-02-27

### Added

- [#3](https://github.com/cloudcreativity/json-api-testing/issues/3)
  Can now assert exact errors on a document and HTTP message.
- [#5](https://github.com/cloudcreativity/json-api-testing/issues/5)
  Can now assert a server generated id with a known id.

### Fixed

- [#6](https://github.com/cloudcreativity/json-api-testing/issues/6)
  Fixed asserting that an error exists with only an integer HTTP status code.
- [#4](https://github.com/cloudcreativity/json-api-testing/issues/4)
  Fixed incorrect diff when asserting a resource identifier on a document that contains a resource object.

## [1.0.0-rc.1] - 2019-01-03

### Added

- New implementation using constraint classes. Assertions are now provided via the `Assert` and `HttpAssert`
  classes, with traits in the `Concerns` namespace for adding these to test classes.

### Changed

- Minimum PHP version is now `7.1`.

### Removed

- Package no longer supports PHPUnit 5.
- The previous implementation was deleted, removing these classes:
  - `AbstractTraversableTester`
  - `DocumentTester`
  - `ErrorsTester`
  - `ErrorTester`
  - `ObjectTester`
  - `ResourceIdentifierTester`
  - `ResourceObjectsTester`
  - `ResourceObjectTester`

## [0.4.0] - 2018-04-29

### Added

- Object tester now had meta test helpers.
- Can now assert the order of resources within the a resource object collection.

## [0.3.0] - 2018-02-08

### Added

- Now supports PHP 5.6 to 7.2.
- Now supports PHPUnit 5.7 to 7.0.

## [0.2.0] - 2017-09-02

### Removed

- This package no longer supports PHP 5.6.
- Updated to PHPUnit v6.

## [0.1.1] - 2017-09-02

### Added

- Can now assert that a resource object is one of multiple types using `assertTypeIs()`.
- Can now assert that the `data` member of a document is `null`.
- Can now assert that a resource object matches an expected structure.
- Can now assert that the `data` member of a document is a resource identifier.
- Can now assert that a document does not contain an `errors` member.

### Changed

- Added a generic JSON API object tester class containing common assertions.

### Fixed

- Resource object type assertion caused a PHP error.

## [0.1.0] - 2017-09-02

Initial commit of classes and tests brought in from `cloudcreativity/json-api` at `0.10.1`.
