# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

[See GitHub Releases](https://github.com/mll-lab/graphql-php-scalars/releases).

## Unreleased

## v6.4.1

### Fixed

- Consider variables in Mixed scalar https://github.com/mll-lab/graphql-php-scalars/pull/35

## v6.4.0

### Added

- Support `thecodingmachine/safe` 3

## v6.3.0

### Added

- Add a new abstract scalar `IntRange`

## v6.2.0

### Added

- Add a new scalar `BigInt`

## v6.1.0

### Added

- Support `egulias/email-validator:^4`

## v6.0.0

### Changed

- Require `webonyx/graphql-php:^15`

### Removed

- Drop support for PHP 7.4

## v5.4.1

### Changed

- Clean up error messages

## v5.4.0

### Added

- Support `spatie/regex` versions 2 and 3

## v5.3.0

### Added

- Support `thecodingmachine/safe` version `^2`

## v5.2.0

### Added

- Add scalars `Date`, `DateTime` and `DateTimeTz`

## v5.1.0

### Added

- Add scalar `Null`

## v5.0.1

### Fixed

- Allow `egulias/email-validator:^2`

## v5.0.0

### Fixed

- Return coerced string value in `Regex::parseValue()`

### Changed

- Require `egulias/email-validator:^3`

### Removed

- Drop support for PHP 7.2 and 7.3

## v4.1.1

### Fixed

- Move `ext-json` to `require` section in `composer.json`

## v4.1.0

### Changed

- Improve error message when values can not be coerced into strings

## v4.0.0

### Added

- Support PHP 8

### Changed

- Rename `Mixed` class to `MixedScalar` because `mixed` is a reserved name in PHP 8.
  The GraphQL name of the scalar is still `Mixed` so the schema does not change.

## v3.1.0

### Added

- Support `webonyx/graphql-php@^14.0.0`

## v3.0.2

### Changed

- Move util functions to class for better autoloading

## v3.0.1

### Fixed

- Export only minimally needed files in distribution package

## v3.0.0

### Changed

- Bump dependencies of various packages

### Removed

- Remove support for PHP 7.1
