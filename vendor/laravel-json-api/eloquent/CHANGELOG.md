# Change Log

All notable changes to this project will be documented in this file. This project adheres to
[Semantic Versioning](http://semver.org/) and [this changelog format](http://keepachangelog.com/).

## Unreleased

## [4.6.0] - 2025-09-30

### Added

- [#44](https://github.com/laravel-json-api/eloquent/pull/44) New `asInteger()` method added to the `DeserializesValue`
  trait.

## [4.5.0] - 2025-02-24

### Added

- Package now supports Laravel 11 and 12.

## [4.4.0] - 2024-11-30

### Added

- Allow `laravel-json-api/core` v4 and v5.

### Fixed

- Remove deprecation notices in PHP 8.4.

## [4.3.1] - 2024-10-31

### Fixed

- [#41](https://github.com/laravel-json-api/eloquent/pull/41) Handle key column not existing in cursor paginator.

## [4.3.0] - 2024-10-13

### Added

- [#38](https://github.com/laravel-json-api/eloquent/pull/38) Added `WhereAll` and `WhereAny` filters.

### Fixed

- [#39](https://github.com/laravel-json-api/eloquent/issues/39) Fixed a bug in the eager loader iterator where include
  paths starting with the same word were incorrectly removed. E.g. `car` and `carOwner` would result in just `carOwner`.

## [4.2.0] - 2024-08-26

### Added

- [#37](https://github.com/laravel-json-api/eloquent/pull/37) Add Eloquent cursor pagination implementation.

## [4.1.0] - 2024-06-26

### Added

- [#36](https://github.com/laravel-json-api/eloquent/pull/36) Support Eloquent dynamic relationships.

## [4.0.0] - 2024-03-12

### Changed

- Package is now licensed under the [MIT license.](./LICENSE)
- **BREAKING** Package now requires Laravel 11.
- Minimum PHP version is now `8.2`.
- Use `assert()` within fillable relation field classes rather as an optimisation.
- [#34](https://github.com/laravel-json-api/eloquent/pull/34) **BREAKING** The soft delete driver now throws an
  exception if the model is not successfully soft-deleted. This can happen if a listener or observer on the model
  aborts the delete operation. Previously the driver just carried on, which was incorrect. The exception has to be
  thrown in this scenario because we are now in an invalid state - what the client requested and what actually
  happened does not match up. If developers want to avoid this scenario, they should use authorization or validation
  logic so that clients get an error response that explains why the soft delete cannot be fulfilled.

### Fixed

- [#31](https://github.com/laravel-json-api/eloquent/pull/31) **BREAKING** Use `self` as return type on Eloquent query
  classes. This is potentially breaking any of these classes have been extended.

## [3.1.0] - 2023-10-29

### Added

- [#30](https://github.com/laravel-json-api/eloquent/pull/30) Allow a wider range of Eloquent relations in
  the `QueryToMany` and `QueryToOne` classes. This means packages
  like [culturegr/custom-relation](https://github.com/culturegr/custom-relation) will work with this package.

## [3.0.1] - 2023-04-03

### Fixed

- [#29](https://github.com/laravel-json-api/eloquent/pull/29) Avoid unnecessary query when there is no need to count a
  relationship.

## [3.0.0] - 2023-02-14

### Changed

- Upgraded to Laravel 10 and set minimum PHP version to `8.1`.

## [2.3.0] - 2023-02-09

### Added

- New `MultiPaginator` that allows a schema to offer multiple different pagination strategies.

## [2.2.1] - 2023-01-23

### Fixed

- [laravel#223](https://github.com/laravel-json-api/laravel/issues/223) Ensure a model always has fresh data from the
  database after a write operation, to prevent stale data on cached relationships.

## [2.2.0] - 2022-12-22

### Added

- [#27](https://github.com/laravel-json-api/eloquent/pull/27) Added the `WhereNull` and `WhereNotNull` filters.

## [2.1.1] - 2022-04-04

### Fixed

- Pass sparse field sets to the `JsonApiBuilder` class, ensuring that they are present on any generated page objects.
  Previously this omission meant that page URLs were missing any fields sent by the client.

## [2.1.0] - 2022-02-20

### Added

- The `Number` field can now be configured to accept numeric strings by calling the `acceptStrings()` method on the
  field.

### Fixed

- The `JsonApiBuilder` class was previously converting a `null` decoded id to an empty string when querting for a
  resource id. This has been fixed to pass `null` to the query builder instead of the empty string, as this was most
  likely the cause of failures in Postgres.

## [2.0.0] - 2022-02-09

### Added

- Added support for PHP 8.1.
- Added support for Laravel 9.

### Changed

- Added return types for internal methods, to remove deprecation warnings on PHP 8.1.
- [#20](https://github.com/laravel-json-api/eloquent/pull/20) **BREAKING** To support PHP 8.1 we needed to rename the
  `ReadOnly` contract and trait. This is because PHP 8.1 introduced `readonly` as a reserved word. The following changes
  were made:
    - `LaravelJsonApi\Eloquent\Contracts\ReadOnly` is now `IsReadOnly`.
    - `LaravelJsonApi\Eloquent\Fields\Concerns\ReadOnly` is now `IsReadOnly`.

## [1.0.1] - 2021-12-08

### Changed

- The maximum PHP version is now 8.0. This is because this package does not work in its current form with PHP 8.1. The
  next major version of this package will support PHP 8.1.

### Fixed

- [laravel#139](https://github.com/laravel-json-api/laravel/issues/139) Fix the `WhereHas` and `WhereDoesntHave` filters
  that have been broken since `1.0.0`. Previously they have been iterating over filters on the schema to which the
  relationship belongs - which is incorrect. They now correctly iterate over the filters on the schema on the other side
  of the relationship (this inverse filter).

## [1.0.0] - 2021-07-31

### Added

- [#16](https://github.com/laravel-json-api/eloquent/pull/16) New filter classes that allow you to filter via a
  relationship's filters:
    - `Has`
    - `WhereHas`
    - `WhereDoesntHave`
- [#18](https://github.com/laravel-json-api/eloquent/pull/18) Extracted some filter code to the `HasColumn` and
  `HasOperator` traits.
- Extracted logic to apply sort and filter parameters to an Eloquent query builder into separate classes:
  `FilterApplicator` and `SortApplicator`.

### Changed

- **BREAKING** Countable relationships are now **not** countable by default. This change has been made as the countable
  feature is **not considered production ready** as we plan to make breaking changes to the implementation. By changing
  the default setting to off, you now have to opt-in to this experimental feature.
- Update the `SoftDeleteDriver` to use `class_uses_recursive` to check if the model support soft-deleting.
- [#15](https://github.com/laravel-json-api/eloquent/pull/15) Change the `Scope::make()` method to use `static` instead
  of `self`.
- Moved the following into the `QueryBuilder` namespace. This change should not affect consuming applications as these
  classes are meant for internal package use:
    - `JsonApiBuilder` class.
    - `ModelLoader` class.
    - `Aggregates` namespace.
    - `EagerLoading` namespace.

### Fixed

- [#14](https://github.com/laravel-json-api/eloquent/pull/14) Allow a `null` value in the filter `HasDelimiter` trait.
- When detecting if a query needs a deterministic order, the page paginator will now also correctly match the qualified
  primary key of the model. Previously only the unqualified column name was matched. In MySql this led to the
  deterministic order overriding an existing descending sort for the primary key.

### Removed

- The cursor pagination implementation has been moved to a separate package:
  [laravel-json-api/cursor-pagination](https://github.com/laravel-json-api/cursor-pagination). This is so that we can
  add support for Laravel's new cursor implementation within this Eloquent package. To migrate, install the new package
  and then change your import statements from `LaravelJsonApi\Eloquent\Pagination\CursorPagination`
  to `LaravelJsonApi\CursorPagination\CursorPagination`.

## [1.0.0-beta.6] - 2021-07-10

### Added

- Developers can now fully control the extraction of attribute values from a model by providing a closure to the
  `extractUsing()` method on attributes. This callback receives the model, the column name, and the serialized value.
  Resource classes are still the recommended way of fully customising serialization of models to JSON:API resource
  objects. However, the `extractUsing()` method is useful where a developer only needs to customise one or two attribute
  values on a resource.

### Fixed

- [#13](https://github.com/laravel-json-api/eloquent/pull/13) The default order by column in the page paginator now has
  the table name added. This fixes problems with pagination on relationships or other joins.

## [1.0.0-beta.5] - 2021-06-02

### Changed

- Updated the `Pagination\ProxyPage::withQuery()` method to remove iterable type-hint that has been removed from the
  page interface. The class was also made `final`, as it is not intended to be extended. Although these changes are
  technically breaking, they are unlikely to affect consuming applications.

### Fixed

- The `JsonApiBuilder` was incorrectly casting `null` to an include paths object. On pages, this would incorrectly
  result in pagination links having an `include=` (empty) parameter. This has been fixed, so include paths will only be
  set on the pagination links if include paths were actually specified.

## [1.0.0-beta.4] - 2021-04-26

### Added

- Schemas now support additional sort field classes, that define how to sort models using sort fields that are not
  attributes. Sort field classes must implement the new `SortField` contract. Three initial sort classes are available:
  `SortColumn`, `SortCountable` and `SortWithCount`.
- Default sort order for resources can now be defined on the Eloquent schema using the `$defaultSort` property.

## [1.0.0-beta.3] - 2021-04-22

### Added

- [#10](https://github.com/laravel-json-api/eloquent/pull/10) The `HasMany` field can now handle detaching models from
  the relationship in three ways. Either it will set the inverse relationship to `null` (the default behaviour), or it
  can delete the related models using either the `Model::delete()` or `Model::forceDelete()` methods. The default
  behaviour matches the behaviour in previous versions, so this change is non-breaking. The behaviour can be configured
  via the `keepDetachedModels()`, `deleteDetachedModels()` and `forceDeleteDetachedModels()` methods.
- The `HasOne` field can now handle detaching a related model from the relationship in three ways. Either it will set
  the inverse relationship columns to `null` (the default behaviour), or it can delete the related model using either
  `Model::delete()` or `Model::forceDelete()`. The default behaviour matches the behaviour in previous versions, so this
  change is non-breaking. The behaviour can be configured via the `keepDetachedModel()`, `deleteDetachedModel()` and
  `forceDeleteDetachedModel()` methods.

## [1.0.0-beta.2] - 2021-04-20

### Added

- When using the `Attribute::fillUsing()` method to customise filling an attribute value into a model, the closure now
  receives the entire validated data as its fourth argument. This allows the closure to use other attributes when
  calculating the value to fill into the model.
- Attribute fields now support the columns being on related models, allowing resources to serialize related models as
  attributes rather than relationships. This is primarily intended for use with Eloquent `belongsTo`, `hasOne`,
  `hasOneThrough` and `morphOne` relations that can have default related models. As part of this feature, the model
  hydrator will now iterate through loaded relations on the model and save any models that are dirty.
- Schemas that have attribute fields with values derived from related models will automatically eager load the
  relationship by adding the relationship to the default eager load paths for the schema.

### Changed

- **BREAKING** The `Contracts\Fillable::fill()` method now expects the entire validated data as its third argument.
- **BREAKING** The `Contracts\Fillable` interface now has a `mustExist()` method. This allows an attribute to indicate
  that the primary model being filled must exist in the database *before* the attribute is filled. This is intended for
  use by attributes that fill related models.
- **BREAKING** The `Contracts\FillableToOne` and `FillableToMany` interfaces now no longer extend the `Fillable`
  interface. This is so that the `fill()` methods can correctly type-hint the related identifier(s) that are expected
  when filling a relationship. Effectively the `Fillable` contract is now intended for use by the `id` field and
  attribute fields.

## [1.0.0-beta.1] - 2021-03-30

### Added

- *To-many* relationships are now countable. This allows a client to specify, via a query parameter, which relationships
  it wants to be counted. These are used by the implementation to load counts on the Eloquent model, so that the count
  values can be added to the relationship's `meta` member. Refer to documentation for implementation details.
- Package now supports encoding of resource IDs. Resource IDs are correctly decoded when querying the database for
  matching models.
- To support ID encoding, the following filters have been added specifically for filtering by resource ids:
    - `WhereIdIn`
    - `WhereIdNotIn`

### Changed

- Made improvements to the eager loading implementation. All classes in the `EagerLoading` namespace are now marked as
  internal, as they are not intended for use outside of this package. The public API is `JsonApiBuilder::with()`,
  `ModelLoader::load()` and `ModelLoader::loadMissing()`.
- Refactored the Eloquent Schema `loader()` method to `loaderFor($modelOrModels)`.
- The `JsonApiBuilder` class now expects the schema container as its first argument. To construct a new `JsonApiBuilder`
  instance, the `Schema::newQuery()` and `Relation::newQuery()` methods should be used.

### Removed

- **BREAKING** Deleted the `Pagination\Concerns\HasPageMeta` trait as the trait is now in the `laravel-json-api/core`
  package as `LaravelJsonApi\Core\Pagination\Concerns\HasPageMeta`.

## [1.0.0-alpha.5] - 2021-03-12

### Added

- [#6](https://github.com/laravel-json-api/eloquent/pull/6) Package now fully supports soft-deleting resources. If a
  model allows soft deleting, but no changes are made to a schema, then deleting the resource will soft-delete it and
  that resource will no longer appear in the API. However, if soft-delete capability is to be exposed to the client, a
  schema should apply the `SoftDeletes` trait from this package and add a `Fields\SoftDelete` field to their list of
  fields. Refer to documentation for full list of capabilities.
- Added the `WithTrashed` and `OnlyTrashed` filter classes.
- The package now supports multi-resource models. This feature allows a model to be represented as more than one JSON:
  API resource class and works by having proxy classes for each additional representation of a model. Refer to
  documentation for examples and details of how to implement multi-resource models.
- [#7](https://github.com/laravel-json-api/eloquent/pull/7) Added a new `MorphToMany` JSON:API relation field. This
  wraps several sub-relation fields and presents them as a single polymorphic relationship. The relationship value works
  both as the `data` member of the relationship object and as a relationship end-point. The relationship is modifiable
  when every sub-relation is writeable (implements the `FillableToMany` relation) and each resource type that can be in
  the relationship maps to a single sub-relation. Include paths also work, with the include paths only being applied to
  the sub-relations for which they are valid.

### Changed

- **BREAKING** Deleting a model now uses `Model::delete` instead of `Model::forceDelete`. This change was required when
  adding full support for soft-deleting resources.
- **BREAKING** Repositories are now injected with a driver which defines the database interactions for the repository.
  This allows database interactions to be modified, without having to rewrite the repository class - and is used as to
  implement the soft-deletes feature.
- **BREAKING** The `sync`, `attach` and `detach` methods on the `FillableToMany` interface now type-hint `iterable` as
  their return type. Previously they type-hinted the Eloquent collection class.
- **BREAKING** The eager load implementation has been modified to support the new polymorphic to-many relation.
  Generally this should not cause any breaking changes, because the eager loading classes were effectively used
  internally to handle eager loading. Changes include removing the `skipMissingFields` methods (that existed in multiple
  locations) and rewriting the `EagerLoadPath` class.

### Removed

- **BREAKING** Remove the following methods from the `Schema` class. These were originally added as convenience methods
  if writing custom controller actions - however, their use is now not suitable as all database querying should be
  executed via the repository class to ensure Eloquent query builders are created according to the database driver that
  is in use. The methods are:
    - `Schema::newQuery()`
    - `Schema::query()`

## [1.0.0-alpha.4] - 2021-02-27

### Added

- The Eloquent schema now has `indexQuery` and `relatableQuery` methods. These allow filtering for authorization
  purposes when a list of resources is being retrieved. For instance, it could filter those queries so that only models
  belonging to the authenticated user are returned.
- Can now determine whether multiple filters should return zero-to-one resource using the `Schema::isSingular()` method.

### Changed

- **BREAKING** The query builder classes have been updated for changes to the interfaces they implement. This adds
  the `withRequest()` method and renames `using()` to `withQuery()`.
- [#2](https://github.com/laravel-json-api/eloquent/issues/2) **BREAKING** The `Fillable` contract now type-hints the
  request class in its read-only method signatures, and allows it to be `null`. The `ReadOnly` trait has been updated,
  so this is unlikely to affect field classes if the trait has been used.
- **BREAKING** If no request class is provided to the `ModelHydrator` class via its new  `withRequest()` method, it is
  now assumed the hydration is occurring outside of a HTTP request. I.e. that the developer is manually triggering the
  hydration. Without the HTTP request, fields will not be checked for their read-only state and will be filled if the
  provided data has a value for the field. Implementing libraries must ensure that `withRequest()` is called when
  filling values provided by a HTTP client.
- **BREAKING** Renamed the `Builder` class to `JsonApiBuilder`. This change was made as it was confusing what
  a `Builder` referred to, because Laravel uses this class name for Eloquent builders.
- The `Relation::type()` method should now be used to set the inverse resource type on a relationship field.

### Fixed

- The `Builder::filters()` method now correctly yields both the schema's filter and the filters from a relationship, if
  one is set. Previously the filters were not yielded correctly if there was a relationship.
- the `QueryToOne` and `QueryToMany` builders now correctly use the model's relation name instead of the JSON:API field
  name when retrieving the relationship object from the model. Previously this would have failed with an error if the
  model relationship name was not the same as the JSON:API field name.

### Deprecated

- The `Relation::inverseType()` method is deprecated and will be removed in `1.0-stable`. Use `Relation::type()`
  instead.

## [1.0.0-alpha.3] - 2021-02-09

### Added

- Relationship fields now implement the new `isValidated()` method, indicating whether the field value should be merged
  with client provided values for an update request. By default, the `BelongsTo` and `MorphTo` relations *are*
  validated, whereas all other fields are not. This is a sensible default, as the `BelongsTo/MorphTo` identifiers are
  stored on the model so are likely to be required for validation. The defaults can be overidden on the fields using
  the `mustValidate()` or `notValidated()` methods.
- Eager loading now supports schemas having default eager load paths. This is set via the `$with` property on the
  schema, which is returned by the public `with()` method.

### Changed

- Moved the existing `EagerLoader`, `EagerLoadMorphs` and `EagerLoadPath` to the `EagerLoading` namespace.

### Fixed

- The `BelongsToMany` field now correctly yields both its own filters and filters from its pivot fields. Previously the
  filters were not yielded correctly if both the field and pivot had filters.

## [1.0.0-alpha.2] - 2021-02-02

### Added

- [#1](https://github.com/laravel-json-api/eloquent/pull/1)
  Eloquent fields now support serializing models to JSON values. This means that resource classes now become optional:
  because in the absence of a resource class, the implementation can fall-back on serializing resources using the
  Eloquent schema.
- **BREAKING** Split the `Arr` field class into two: `ArrayList` and `ArrayHash`. This was required because now that the
  fields are also serializing values, the handling of empty values is different depending on whether it is a list (empty
  array) or a hash (empty array converted to `null`).
- Can now set the URI field name for a relationship on the schema's relationship field, using the
  `withUriFieldName()` method. Alternatively, the `retainFieldName()` method can be used to retain the field name as-is.

## [1.0.0-alpha.1] - 2021-01-25

Initial release.
