<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

namespace LaravelJsonApi\Contracts\Routing;

use LaravelJsonApi\Contracts\Auth\Authorizer;
use LaravelJsonApi\Contracts\Schema\Relation;
use LaravelJsonApi\Contracts\Schema\Schema;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

interface Route
{

    /**
     * Get the resource type.
     *
     * @return string
     */
    public function resourceType(): string;

    /**
     * Get the resource id or the model (if bindings have been substituted).
     *
     * @return object|string|null
     */
    public function modelOrResourceId();

    /**
     * Does the URL have a resource id?
     *
     * @return bool
     */
    public function hasResourceId(): bool;

    /**
     * Get the resource id.
     *
     * @return string
     */
    public function resourceId(): string;

    /**
     * Get the resource model.
     *
     * @return object
     */
    public function model(): object;

    /**
     * Get the field name for a relationship URL.
     *
     * @return string
     */
    public function fieldName(): string;

    /**
     * Get the schema for the current route.
     *
     * @return Schema
     */
    public function schema(): Schema;

    /**
     * Get the authorizer for the current route.
     *
     * @return Authorizer
     */
    public function authorizer(): Authorizer;

    /**
     * Does the URL have a relation?
     *
     * @return bool
     */
    public function hasRelation(): bool;

    /**
     * Get the inverse schema for a relationship route.
     *
     * For example, the URL `/api/posts/123/comments` would
     * return the comments schema as the inverse schema.
     *
     * @return Schema
     */
    public function inverse(): Schema;

    /**
     * Get the relation for a relationship URL.
     *
     * @return Relation
     */
    public function relation(): Relation;

    /**
     * Substitute the route bindings onto the Laravel route.
     *
     * @return void
     * @throws HttpExceptionInterface
     */
    public function substituteBindings(): void;
}
