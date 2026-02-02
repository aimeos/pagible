# Change Log

All notable changes to this project will be documented in this file. This project adheres to
[Semantic Versioning](http://semver.org/) and [this changelog format](http://keepachangelog.com/).

## Unreleased

## [5.2.1] - 2025-10-10

### Changed

- Improve exception message when resource container cannot resolve a value to a JSON:API resource.

## [5.2.0] - 2025-02-24

### Added

- Package now supports both Laravel 11 and 12.

## [5.1.0] - 2025-01-23

### Added

- [#24](https://github.com/laravel-json-api/core/pull/24) Add `header` property to error source object.

## [5.0.2] - 2025-01-11

### Fixed

- [#23](https://github.com/laravel-json-api/core/pull/23) Ensure relationship meta is included in paginated relationship
  responses.

## [5.0.1] - 2024-11-30

### Fixed

- Removed more PHP 8.4 deprecation notices.

## [5.0.0] - 2024-11-29

### Added

- **BREAKING**: [#21](https://github.com/laravel-json-api/core/pull/21) The `Authorizer` contract now allows all methods
  to return a `bool` or an Illuminate authorization response.

## [4.3.2] - 2024-11-30

### Fixed

- Remove more PHP 8.4 deprecation notices.

## [4.3.1] - 2024-11-29

### Fixed

- Reverted [#19](https://github.com/laravel-json-api/core/pull/19) as the changes were breaking, so should not have been
  released as a minor version.

## [4.3.0] - 2024-11-26

### Added

- [#19](https://github.com/laravel-json-api/core/pull/19) The `Authorizer` contract now allows all methods to return a
  `bool` or an authorizer response.

### Fixed

- Removed deprecation notices in PHP 8.4.

## [4.2.0] - 2024-08-21

### Added

- Response classes now have a `withoutHeaders()` method to remove headers from the response.

### Fixed

- [#18](https://github.com/laravel-json-api/core/pull/18) Ensure headers are merged when using the `withHeaders()`
  method on the JSON:API response classes. This was previously not merging headers, which was not correct and therefore
  this is a bug fix. If you were relying on this behaviour, use the new `withoutHeaders()` method to remove any headers.

## [4.1.0] - 2024-06-26

### Fixed

- [#17](https://github.com/laravel-json-api/core/pull/17) Fix incorrect `self` link in related resource responses, and
  remove `related` link that should not exist. This has been incorrect for some time, but is definitely what
  the [spec defines here.](https://jsonapi.org/format/1.0/#document-top-level)

## [4.0.0] - 2024-03-12

### Changed

- Package is now licensed under the [MIT License.](./LICENSE)
- **BREAKING** Package now requires Laravel 11.
- Minimum PHP version is now `8.2`.

## [3.3.0] - 2023-11-08

### Added

- Can now create a JSON:API server once, without it being thread-cached. This is required for registering routes, as we
  do not expect that server instance to be used again after routes are registered. This matches production,
  where `route:cache` should have been used, so it would be inaccurate to leave the server thread-cached after
  registering routes in a non-production environment e.g. test.

## [3.2.0] - 2023-07-20

### Added

- [#12](https://github.com/laravel-json-api/core/pull/12) Add `ulid()` method to the matches ids trait.

### Fixed

- [#10](https://github.com/laravel-json-api/core/pull/10) Use correct resolver for resource type.

## [3.1.0] - 2023-03-12

### Added

- [#9](https://github.com/laravel-json-api/core/pull/9) Can now cast a `stdClass` object to a `Hash` via
  the `Hash::cast()` method.

## [3.0.0] - 2023-02-14

### Changed

- Upgrade to Laravel 10 and set minimum PHP version to `8.1`.

## [2.4.0] - 2023-01-15

### Added

- [#8](https://github.com/laravel-json-api/core/pull/8) Allow relations to be conditionally eager loadable.

## [2.3.0] - 2022-04-17

### Added

- The `JsonApiException` now has a `context()` method. The Laravel exception handler uses this to obtain additional
  context information to log when logging the exception. This method returns the status code and the JSON:API errors so
  that these can be seen if a JSON:API exception is logged.

## [2.2.0] - 2022-04-01

### Added

- New `Document\ResourceIdentifier::idIsEmpty()` static method for checking that an `id` value is not empty. This check
  ensures that the trimmed string is empty and that the string is not `"0"`, as zero could be used for a resource id.
  This new static method has been added to ensure the logic of determining if an id value is empty is in one place.

### Fixed

- The `Document\ResourceObject` class now accepts an id of `"0"`. Previously this was treated as empty, which is
  incorrect.

## [2.1.0] - 2022-02-20

### Added

- The `LazyRelation` class now has an `all()` method to get the related resources for a to-many relation as an array.
- The `LazyRelation` class now caches the to-one resource. Previously it only cached the to-many resources, so this
  change makes the behaviour consistent.

### Fixed

- Previously the `LazyRelation` class would always throw an exception if iterated over. This has now been fixed.

## [2.0.0] - 2022-02-08

### Added

- Package now supports Laravel 9.
- Added two new utility classes - `Support\AppResolver` and `Support\ContainerResolver` - that lazily resolve the
  current application and container instances. These have been added to enable support for Laravel Octane, which
  recommends injecting a closure resolver for getting that current instances of the application or container. These
  utility classes enable strictly type-hinted constructor dependency injection, as these classes are more specific about
  what the resolver than just type-hinting the generic `\Closure` class which could return anything.

### Changed

- Added return types for internal methods, to remove deprecation warnings on PHP 8.1.
- **BREAKING**: Made the following changes to support Laravel Octane:
    - The `Schema\Container` class now takes an instance of `Support\ContainerResolver` as its first constructor
      argument. This allows the schema container to lazily load the current container instance.
    - The `Server\Server` class now takes an instance of `Support\AppResolver` as its first constructor argument. In
      addition, the `$app` property has been made private, and the deprecated `$container` property has been removed.
      Child classes that need to access either the application or the container should use the new protected `app()`
      method. This change allows the server instance to lazily load the current application instance.
    - The `Server\ServerRepository` class now only has a single constructor argument, which is an instance
      of `Support\AppResolver`. The private `$config` property has also been removed. This change allows the server
      repository to lazily load the current application instance.

## [1.1.1] - 2022-02-08

### Added

- Added a `JsonApiException` test as this class requires breaking changes when upgrading Symfony to the next version.

## [1.1.0] - 2022-01-03

### Added

- Relationship response classes now merge relationship links with the links set on the response classes. This can be
  disabled by calling the `withoutRelationshipLinks()` method on these classes.
- The `ConditionalField` class now has a `value()` method to get the value of the field without checking whether the
  field is meant to be skipped.
- The `ConditionalFields` class now has a public `values()` method to get the values of the fields without checking
  whether the fields are meant to be skipped.
- [laravel-#127](https://github.com/laravel-json-api/laravel/issues/127) The `JsonApiResource` class now has a
  protected `serializeRelation()` method, that allows a developer to customise the serialization of a JSON:API
  resource's relationship beyond the default implemented by this package.
- The resource `Relation` class now has two additional helper methods: `onlySelfLink()` and `onlyRelatedLink()`.
- Added new features to the `Links` object:
    - It now implements `ArrayAccess`.
    - New `has()` method for checking whether a key exists in the links object.
    - New `hasSelf()` and `getSelf()` methods for accessing the `self` link defined by the JSON:API spec.
    - New `hasRelated()` and `getRelated()` methods for accessing the `related` link defined by the JSON:API spec.
    - New `all()` method for getting the links as an array.

### Fixed

- [laravel-#130](https://github.com/laravel-json-api/laravel/issues/130) The `JsonApiResource::relationship()` method
  now safely iterates over relationships even if they are conditional. This means the named relationship will always be
  found even if it is marked to be skipped when serializing the resource. This is implemented through a
  new `RelationIterator` class.
- [laravel-#105](https://github.com/laravel-json-api/laravel/issues/105) The relationship response classes now handle
  the relationship not existing on the resource. This can happen if a developer has marked the relationship as hidden.
  While it is not recommended to hide relationships that have relationship endpoints, this gracefully handles this
  scenario when merging links and meta into the top-level document response.

## [1.0.0] - 2021-07-31

### Fixed

- Ensure the `DataResponse` class passes on its created flag if its data member is already a `JsonApiResource`.
- Only add the `Location` header to a response if the resource has a `self` URL. Previously the header would be set with
  a `null` value.

## [1.0.0-beta.5] - 2021-07-10

### Added

- [#6](https://github.com/laravel-json-api/core/issues/6) The authorizer contract now has a `showRelated` method to
  authorize the show-related controller action. Previously the `showRelationship` method was used to authorize both the
  show-related and show-relationship controller actions. This change means that authorizers can implement different
  authorization logic if needed. However, our default authorizer (the `Auth\Authorizer` class) remains unchanged in that
  both actions expect there to be a `view<RelationshipName>` method on the policy to authorize these actions.
- The `JsonApiException` class now has `is4xx()` and `is5xx()` helper methods for determining whether the HTTP status
  code is in the 4xx or 5xx range.

### Changed

- The `Schema\Schema` class no longer sorts fields by their name. This means fields are now processed in the order that
  they are defined by the developer. Fields can be listed in alphabetical order by the developer if that is the desired
  order.
- The `Auth\Authorizer` class is no longer `final` and can now be extended if needed.
- Moved the package JSON:API version to a constant on the `JsonApiService` class.

## [1.0.0-beta.4] - 2021-06-02

### Changed

- **BREAKING** Removed the iterable type-hint from the `Contracts\Pagination\Page::withQuery()` method. The value passed
  can be any value that can be cast to a `Core\Query\QueryParameters` object. This change also affects the
  `Core\Pagination\AbstactPage::withQuery()` method, that has been updated to remove the type-hint. This will affect any
  child classes that have overloaded this method.
- **BREAKING** Remove the iterable type-hint from the `Core\Resources\ResourceCollection::withQuery()` method. The value
  passed can be any value that can be cast to a `Core\Query\QueryParameters` object.

### Fixed

- Ensure internal response classes all consistently use the `links()` method when passing links to the encoder. This
  fixes a bug whereby pagination links were not added to the compound document for related resources and relationship
  identifier responses.
- Query parameters passed to the abstract page object were not correctly encoding to a query string. This is because the
  `collect()` method was being used, which meant `QueryParameters::toArray()` would be used to serialize query
  parameters. This has now been updated to use `QueryParameters::toQuery()` instead, which is the correct method to use.

## [1.0.0-beta.3] - 2021-04-26

### Added

- Include paths and sparse field sets that should be used when encoding JSON:API responses can now be manually set on
  response classes using the `withIncludePaths()` and `withSparseFieldSets()` methods, or using the convenience
  `withQueryParameters()` method to set both from a query parameters object. When include paths and/or sparse field sets
  are set on the response, these are used when encoding the response JSON instead of determining these query parameters
  from the request. If no include paths or sparse field sets are set on the response, the previous behaviour of
  determining these from the request is used.

### Changed

- **BREAKING** Methods relating to include paths and sparse field sets have been moved from the
  `Responses\Concerns\IsResponsable` trait to a new `Responses\Concerns\HasEncodingParameters` trait. As part of this
  change, the previous `protected` method `fieldSets()` has been renamed `sparseFieldSets()`.
- **BREAKING** Made several changes to interfaces for sort fields:
    - The `Attribute` and `ID` interfaces no longer implement `Sortable` and instead have a `isSortable()` method
      directly defined on their interface.
    - The `Sortable` interface is now intended to be implemented on a class that is an additional sort field to those
      that are attributes. It has one method: `sortField()` which returns the name of the sort field.
    - The `Schema::isSortable()` method has been renamed `isSortField()`. This makes it clearer that the method is
      querying whether the provided name is a valid sort field.
    - The `Schema::sortable()` method has been renamed `sortFields()`. This makes it clearer that the method is
      returning a list of the sort field names.
    - Added the `sortField()` and `sortables()` methods to the `Schema` interface.

## [1.0.0-beta.2] - 2021-04-20

### Added

- The schema container now supports resolving schemas for models where the schema is registered against a parent class
  or interface. Parent classes are matched *before* interfaces, and if a match is found the resolution is cached to
  ensure the resolution logic runs once per model class.
- The resource factory class now looks up schemas for a model first, then retrieves the fully-qualified resource class
  from the matched schema. By delegating to the schema like this, the resource container can now convert models to
  resources where a schema has been registered for a parent class or interface.
- The `Contracts\Resources\Factory` interface now has a `canCreate()` method to determine whether the factory can create
  a JSON:API resource for the supplied model.
- The `Contracts\Schema\Container` interface now has a `existsForModel()` method, to determine whether a schema exists
  for the supplied model class.

### Changed

- The `Core\Resources\Container` class now expects a single factory instance to its constructor. This was changed as
  there was no requirement for multiple resource factories to be loaded into the container. The container still supports
  injecting a factory, as this allows the creation of resources by the container to be customised, without having to
  re-implement the logic within the container class. As part of this change, the `Container::attach()` method was also
  removed.
- The `Core\Resources\Factory` class constructor was amended to only expect a schema container. Additionally the method
  signature of the protected `build()` method was changed to receive a schema instance and the model being converted to
  a JSON:API resource.
- The `Core\Server\Server` and `Core\Server\ServerRepository` classes are now injected with the Laravel application
  instance, instead of just type-hinting the container. This change was made to allow code within servers to access the
  application environment, using `$this->app->environment()` rather than having to use `app()->environment()`
  (which used to be the case as the injection was only type-hinted as the container contract).

### Fixed

- The base `Server` class now correctly passes extra parameters in its `url()` method. Previously these were passed to
  Laravel's `url()` helper - but that helper only appends extra parameters if there is no HTTP host in the provided
  path. The server's `url()` method now passes these as we *always* went them appended, regardless of whether the API's
  base path has a HTTP host or not.
- Include paths, sort fields and countable paths now correctly parse empty values. Previously an error was caused by
  attempting to cast an empty string to the relevant query objects.

### Deprecated

- The `Core\Server\Server::$container` property is deprecated and will be removed in `1.0.0-stable`. Child classes
  should use the new `Server::$app` property instead.

### Removed

- The `Contracts\Resources\Factory::handles()` method has been removed in favour of using the new `canCreate()` method
  instead.
- The `Contracts\Schema\Container::resources()` method has been removed, in favour of resource factories using the
  schema container's `existsForModel()` and `schemaForModel()` methods.

## [1.0.0-beta.1] - 2021-03-30

### Added

- **BREAKING** Added the following methods to the `Contracts\Schema\Schema` interface: `isFilter()`,
  `isSparseField`, `isSortable()` and `hasSelfLink()`. These methods have been added to the abstract schema class
  provided by this package, so this is unlikely to have a significant impact on implementing packages.
- **BREAKING** Made the following changes to the `Contracts\Query\QueryParameters` interface:
    - New `unrecognisedParameters` method. This returns any query parameters that are not defined by the JSON:API
      specification, which allows implementations to add support for additional query parameters as needed.
    - The `filters` method now returns a `FilterParameters` object or null. Previously it returned an array or null.
- **BREAKING** The `$baseUri` argument on the `Contracts\Resources\Serializer\Relation` interface is now nullable.
- **BREAKING** The `Contracts\Store\Store` interface now has a `findOrFail` method. This is unlikely to be breaking in
  most implementations because the `Core\Store\Store` class will be in use and has been updated.
- **BREAKING** Added a `cast` method to the `Contracts\Resources\Container` interface. This is unlikely to be breaking
  in most implementations because the `Core\Resources\Container` class will be in use and has been updated.
- New `Contracts\Schema\IdEncoder` interface to encode model IDs to JSON:API resource IDs.
- New `FilterParameters` class for handling a collection of filter parameters received from a client.
- The `FieldSets`, `IncludePaths` and `SortFields` classes all now have a `collect()` method, that returns a collection
  object.
- The `IncludePaths` and `SortFields` classes now have `filter`, `reject` and `forSchema` methods.
- The `SortField` class now has static `ascending` and `descending` methods, to easily create a sort field with the
  specified direction.
- The `QueryParameters` class now has a `toQuery()` method, that casts the value back to a HTTP query parameter array.
  This is different from `QueryParameters::toArray()`, as the `include` and `sort` parameters are strings in a HTTP
  query array.
- The `QueryParameters` class now has a `forSchema()` method, that returns a new query parameters instance that contains
  only parameters valid for the supplied schema.
- The `Document\ResourceObject` class has a new `withRelationshipMeta` method for adding meta for a specified
  relationship.
- Added new response classes for returning related resources for a relationship - e.g. the `/api/posts/1/comments`
  endpoint. Previously the `DataResponse` class was used for this endpoint, but the new classes allow for relationship
  meta to be merged into the top-level meta member of the response for the endpoint.
- The core package now supports the *countable* implementation-semantic. This adds a custom query parameter that allows
  a client to specify which relationships should have a count added to their relationship meta.
- Added a number of pagination traits - `HasPageMeta` and `HasPageNumbers`, so that these can be used in both the
  Eloquent and non-Eloquent implementations.
- Added a `dump` method to the `Core\Document\ResourceObject` class.

### Changed

- **BREAKING** The return type of `Contracts\Schema\Schema::repository()` is now nullable.
- **BREAKING** The return type of `Contracts\Store\Store::resources()` is now nullable.
- **BREAKING** Made a number of changes to store contracts, so that the contracts are easier to implement in when not
  working with Eloquent resources:
    - The `QueryAllBuilder` contract has been removed; support for singular filters is now implemented via the
      `HasSingularFilters` interface which is intended to be added to classes implementing `QueryManyBuilder`. As part
      of this change, the `QueriesAll::queryAll()` method now has the `QueryManyBuilder` interface as its return type.
    - The `QueryManyBuilder` contract no longer has pagination methods on it. If a builder supports pagination, it must
      add the `HasPagination` interface.
    - Removed the `cursor` method from the `QueryManyBuilder` contract, as it is not required on the contract
      (implementing classes can add it if needed). The `get` method now has a return type of `iterable` instead of the
      Laravel `Collection` class.
- **BREAKING** The `Contracts\Encoder\Encoder` interface now has two methods for encoding resource identifiers:
  `withToOne` and `withToMany`. These replace the `withIdentifiers` method, which has been removed.
- Moved the following classes from the `Core\Responses` namespace to the `Core\Responses\Internal` namespace. This is
  considered non-breaking because the classes are not part of the public API (responses that can be used for the public
  API are still in the `Core\Responses` namespace):
    - `PaginatedResourceResponse`
    - `ResourceCollectionResponse`
    - `ResourceIdentifierCollectionResponse`
    - `ResourceIdentifierResponse`
    - `ResourceResponse`

### Removed

- Deleted the `Core\Responses\Concerns\EncodesIdentifiers` trait. This is considered non-breaking as the trait was only
  intended for internal use.

### Fixed

- The `QueryParameters::setFieldSet()` method now correctly passes the fields lists as an array to the field set
  constructor.
- Fixed the `Core\Document\ResourceObject::merge()` method handling of merging relationships. Previously this used
  `array_replace_recursive` to megre the relationship object, but this led to incorrect merging of `data` members,
  particularly for to-many relationships. This has been altered to `array_replace`, so that the `data`, `links` and
  `meta` members of the relationship are replaced with the values from the resource object's relationship that is being
  merged in.

## [1.0.0-alpha.5] - 2021-03-12

### Added

- If closures are used for data and/or meta on the  `Resources\Relation` class, the closures will now receive the model
  as their first and only argument.
- The default value of the `Resources\Relation` class is now returned by a protected `value` method, allowing child
  classes to modify the default behaviour if needed.
- New `Creatable` interface, which the `JsonApiResource` class delegates to when determining whether a resource was
  created within the current HTTP request.

### Changed

- The `$container` property on the `Server` class is now `protected` and can be used by child classes if needed.
- The `$resource` property on the `Resources\Relation` class is now `protected`.

### Removed

- **Reverted [#3](https://github.com/laravel-json-api/core/pull/3)** Server classes can no longer use constructor
  dependency injection. This is because server classes are created in a number of different contexts - e.g. HTTP
  requests, Artisan generators, etc - so injecting dependencies via the constructor will likely result in developers
  injecting dependencies that are only required in certain contexts in which the server is being used. See
  [laravel #44](https://github.com/laravel-json-api/laravel/issues/44) for discussion on this topic.
- The `Server\Server` contract no longer has a `serving()` method on it. This has been removed from the contract so that
  developers can type-hint dependencies in their `serving` method.

## [1.0.0-alpha.4] - 2021-02-27

### Added

- **BREAKING** The builder interfaces in the `Contracts\Store` namespace now have a `withRequest` method. This allows
  passing the request into the builder process as context for the action.
- **BREAKING** The `Contracts\Routing\Route` contract now has an `authorizer` method, for getting the authorizer
  instance for the route.
- **BREAKING** The `Contracts\Schema\Schema` contract now has a `url` method, for generating a URL for the resource that
  the schema defines.
- **BREAKING** Added the `allInverse()` method to the `Contracts\Schema\Relation` contract. This returns a list of the
  allowed resource types for the relationship. Typically this will just be one resource type; polymorphic relations will
  return multiple.
- New `get` method on the `ConditionalField` class for retrieving the value of the field.
- Response classes now have a `withServer` method, for explicitly setting the JSON:API server to use when generating the
  response. This is useful when returning responses from routes that do not have the JSON:API middleware applied to
  them.
- New `MetaResponse` class for returning a JSON:API response with a document containing a top-level `meta` value.
- [#3](https://github.com/laravel-json-api/core/pull/3) Server classes are now resolved via the service container,
  allowing the developer to use constructor dependency injection if desired.
- The `DataResponse` class now has a `didntCreate` method for ensure the resource response does not have a `201 Created`
  status.

### Changed

- **BREAKING** The `Contracts\Auth\Authorizer` contract now requires the model class to be passed as the second argument
  on the `index` and `store` methods. Also, all methods have been updated to type-hint the Illuminate request object.
- **BREAKING** The `using` method has been renamed to `withQuery` on the following interfaces in the `Contracts\Store`
  namespace:
    - `QueryManyBuilder`
    - `QueryOneBuilder`
    - `ResourceBuilder`
    - `ToManyBuilder`
    - `ToOneBuilder`
- **BREAKING** The `Contracts\Resources\Serializer\Hideable` contract has been updated to type-hint the request class in
  its method signatures.
- **BREAKING** The `Core\Schema\SchemaAware` trait has been moved to the `Core\Schema\Concerns` namespace, for
  consistency with other traits.
- **BREAKING** The `Core\Schema\Container` class now expects the server instance to be passed as its second constructor
  argument, with the list of schemas now its third constructor argument.
- **BREAKING** The constructor argument for the abstract `Core\Schema\Schema` class has been changed to the server
  instance that the schema belongs to. This change was made so that schemas can generate URLs using the server instance,
  while also injecting the server's schema container into fields if needed.
- The server repository now caches servers it has created, and should now be registered in the service container as a
  singleton.

### Fixed

- Fixed parsing the `fields` query parameter to the `Core\Query\FieldSets` and `Core\Query\FieldSet` classes.

## [1.0.0-alpha.3] - 2021-02-09

### Added

- **BREAKING** The `Contracts\Schema\Relation` contract now has a `isValidated()` method, to determine if the relation
  value should be merged when validating update requests. There is now a `Core\Schema\Concerns\RequiredForValidation`
  trait that can be used on relationship fields to implement the required method.
- New features for the `Core\Documents\ResourceObject` class:
    - New `merge()` method for merging two resource objects together. This is useful for update validation, where the
      values supplied by a client need to be merged over the existing resource field values.
    - The `putRelation` and `replace` methods now accept an instance of `UrlRoutable` for the `id` value of to-one or
      to-many relations.
- The schema container instance is now injected into schema classes via the constructor `$schemas` property. This has
  been added so that a schema class can be instantiated directly from the service container if the schema container is
  bound in the service container.
- New `Core\Resources\ConditionalList` class, for iterating over conditional attributes but yielding them as a
  zero-indexed array list.

### Changed

- The `Core\Document\ResourceObject::withoutLinks()` method now correctly removes both resource links and relationship
  links.
- **BREAKING** As conditional values are now supported in relationships (previously only supported in attributes), the
  following have been renamed to make it clear that they are not just for use in attributes:
    - The `Core\Resources\Concerns\ConditionallyLoadsAttributes` trait is now `ConditionallyLoadsFields`.
    - The `Core\Resources\ConditionalAttr` is now `ConditionalField`.
    - The `Core\Resources\ConditionalAttrs` is now `ConditionalFields`.
- **BREAKING** The first argument on the `Contracts\Server\Server` interface (`$parameters`) has been made optional.

### Removed

- **BREAKING** Removed the `mustValidate()` and `isValidated()` methods from the `Core\Resources\Relation` class. These
  fields are now defined on the schema's relation field instead of the resource's relation.
- **BREAKING** Made changes to the `Core\Documents\ResourceObject` class:
    - Removed the deprecated `create()` method, as this was never intended to be brought in from the old package.
    - Remove the `Arrayable` contract (and therefore the `toArray()` method). This is because `toArray()` was always
      ambiguous - would it return the field values, or the JSON representation of the resource? Replace `toArray()`
      with `jsonSerialize()`. The `all()` method continues to return the field values.

## [1.0.0-alpha.2] - 2021-02-02

### Added

- [#2](https://github.com/laravel-json-api/core/pull/2)
  **BREAKING** The `Core\Resources\JsonApiResource` is no longer abstract, and now expects the schema *and* the model to
  be injected via its constructor. It will use the schema to convert a model to a JSON:API resource. This allows the
  resource classes to be optional, as the resource resolution logic can now fall-back to the `JsonApiResource` when no
  specific resource class exists. Schema fields must implement the `Contracts\Resources\Serializer\Attribute`
  and `Contracts\Resources\Serializer\Relation` interfaces on their fields for the serialization to work.
- **BREAKING** The `Contracts\Encoder\Encoder` contract now has a `withRequest` method to inject the current HTTP
  request into the encoding process. The response classes have been updated to pass the request through to the encoder
  in their `toResponse()` methods.
- **BREAKING** The `Contracts\Schema\Container` contract now has a `schemaForModel` method to lookup a schema by
  providing either a model instance, or the fully-qualified class name of a model.
- **BREAKING** The `Contracts\Schema\ID` contract now has a `key()` method, that can return the model key for the ID.
- **BREAKING** The `Contracts\Schema\Schema` contract now has new methods:
    - `uriType()` which returns the resource type as it appears in URIs.
    - `idKeyName()` which returns the object key for the `id` value.
- New `Contracts\Resources\JsonApiRelation` contract for the relation object returned by the
  `JsonApiResource::relationships()` method. This has the methods on it that encoders can rely on when encoding the
  relationship to JSON.
- **BREAKING** The `Contracts\Schema\Relation` contract now has a `uriName()` method for retrieving the relationship's
  field name as it appears in a URI. The `JsonApiResource`
  class now automatically injects this value from the schema field into the resource relation object.
- New `Core\Resources\ConditionalIterator` class for iterating over values that could contain conditional attributes.

### Changed

- **BREAKING** The `attributes`, `relationships`, `meta` and `links` method of the `JsonApiResource`
  class now require the request to be passed as a parameter. This is to bring the resource in line with Laravel's
  Eloquent resource, though our implementation allows the request to be `null` to cater for resource encoding outside of
  HTTP requests (e.g. queued broadcasting). Additionally, the `relationship` method return type has been changed to the
  new `Contracts\Resources\JsonApiResource`
  contract.
- **BREAKING** The `exists` and `create` methods on the `Contracts\Resources\Container` contract now correctly type-hint
  the parameter as an `object`.
- **BREAKING** The `createResource` method on the `Contracts\Resources\Factory` contract now correctly type-hints the
  parameter as an `object`.
- **BREAKING** The constructor of the `Core\Resources\Factory` class now expects a schema container and an optional
  array of resource bindings (instead of an iterable). If a `null` value is provided for the bindings, the bindings will
  be retrieved from the schema container. Additionally, the protected
  `build` method signature has been updated to correctly type-hint the second argument as an `object`.
- **BREAKING** The constructor arguments for the `Core\Resources\Relation` class have been changed so that it now
  receives the model and base URI - rather than the `JsonApiResource` object. This change was made so that it can be
  used more broadly.

### Removed

- **BREAKING** The `attach` and `attachAll` methods of the `Core\Resources\Factory` class have been removed, because
  they were not in use.

## [1.0.0-alpha.1] - 2021-01-25

Initial release.
