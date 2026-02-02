<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Contracts\Schema;

use LaravelJsonApi\Contracts\Pagination\Paginator;
use LaravelJsonApi\Contracts\Store\Repository;
use Traversable;

interface Schema extends Traversable
{

    /**
     * Get the JSON:API resource type.
     *
     * @return string
     */
    public static function type(): string;

    /**
     * Get the fully-qualified class name of the model.
     *
     * @return string
     */
    public static function model(): string;

    /**
     * Get the fully-qualified class name of the resource.
     *
     * @return string
     */
    public static function resource(): string;

    /**
     * Get the fully-qualified class name of the authorizer.
     *
     * @return string
     */
    public static function authorizer(): string;

    /**
     * Get a repository for the resource.
     *
     * Schemas MUST return a repository if the resource type is retrievable
     * by its resource ID; or if the resource type will be referenced in a JSON:API
     * document via a resource identifier (i.e. when parsing JSON:API documents, the
     * resource type and id can be validated via the repository class.)
     *
     * @return Repository|null
     */
    public function repository(): ?Repository;

    /**
     * Get the resource type as it appears in URIs.
     *
     * @return string
     */
    public function uriType(): string;

    /**
     * Get a URL for the resource.
     *
     * @param mixed $extra
     * @param bool|null $secure
     * @return string
     */
    public function url($extra = [], ?bool $secure = null): string;

    /**
     * Do resources of this type have a `self` link?
     *
     * The `self` link of a resource identifies a specific resource.
     * Servers MUST respond to a `GET` request to the specified URL with a response that
     * includes the resource as the primary data.
     *
     * Typically schemas will return `true` for this method; however there are some
     * instances where a resource may not be retrievable by its id. For example, a resource
     * that can only be obtained from an index (fetch-many) query and has a random UUID
     * that it cannot then be retrieved by. In this scenario, `false` must be returned
     * by this method. In doing so, the resource will also not be able to have relationship
     * links.
     *
     * @return bool
     */
    public function hasSelfLink(): bool;

    /**
     * Get the "id" field.
     *
     * @return ID
     */
    public function id(): ID;

    /**
     * Get the key name for the resource "id".
     *
     * If this method returns `null`, resource classes should fall-back to a
     * sensible defaults. E.g. for `UrlRoutable` objects, the implementation can
     * fallback to `UrlRoutable::getRouteKey()` to retrieve the id value and
     * and `UrlRoutable::getRouteKeyName()` if it needs the key name.
     *
     * @return string|null
     */
    public function idKeyName(): ?string;

    /**
     * Get all the field names.
     *
     * @return array
     */
    public function fieldNames(): array;

    /**
     * Does the named field exist?
     *
     * @param string $name
     * @return bool
     */
    public function isField(string $name): bool;

    /**
     * Get a field by name.
     *
     * @param string $name
     * @return Field
     */
    public function field(string $name): Field;

    /**
     * Get the resource attributes.
     *
     * @return Attribute[]|iterable
     */
    public function attributes(): iterable;

    /**
     * Get an attribute by name.
     *
     * @param string $name
     * @return Attribute
     */
    public function attribute(string $name): Attribute;

    /**
     * Does the named attribute exist?
     *
     * @param string $name
     * @return bool
     */
    public function isAttribute(string $name): bool;

    /**
     * Get the resource relationships.
     *
     * @return Relation[]|iterable
     */
    public function relationships(): iterable;

    /**
     * Get a relationship by name.
     *
     * @param string $name
     * @return Relation
     */
    public function relationship(string $name): Relation;

    /**
     * Does the named relationship exist?
     *
     * @param string $name
     * @return bool
     */
    public function isRelationship(string $name): bool;

    /**
     * Is the provided name a filter parameter?
     *
     * @param string $name
     * @return bool
     */
    public function isFilter(string $name): bool;

    /**
     * Get the filters for the resource.
     *
     * @return Filter[]|iterable
     */
    public function filters(): iterable;

    /**
     * Get the paginator to use when fetching collections of this resource.
     *
     * @return Paginator|null
     */
     public function pagination(): ?Paginator;

    /**
     * Get the include paths supported by this resource.
     *
     * @return string[]|iterable
     */
     public function includePaths(): iterable;

    /**
     * Is the provided field name a sparse field?
     *
     * @param string $fieldName
     * @return bool
     */
     public function isSparseField(string $fieldName): bool;

    /**
     * Get the sparse fields that are supported by this resource.
     *
     * @return string[]|iterable
     */
     public function sparseFields(): iterable;

    /**
     * Is the provided name a sort field?
     *
     * @param string $name
     * @return bool
     */
     public function isSortField(string $name): bool;

    /**
     * Get the parameter names that can be used to sort this resource.
     *
     * @return string[]|iterable
     */
     public function sortFields(): iterable;

    /**
     * Get a sort field by name.
     *
     * @param string $name
     * @return ID|Attribute|Sortable
     */
     public function sortField(string $name);

    /**
     * Get additional sortables.
     *
     * Get sortables that are not the resource ID or a resource attribute.
     *
     * @return Sortable[]|iterable
     */
     public function sortables(): iterable;

    /**
     * Determine if the resource is authorizable.
     *
     * @return bool
     */
     public function authorizable(): bool;
}
