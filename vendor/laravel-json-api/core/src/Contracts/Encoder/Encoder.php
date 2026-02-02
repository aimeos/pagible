<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

namespace LaravelJsonApi\Contracts\Encoder;

use Illuminate\Http\Request;
use LaravelJsonApi\Core\Resources\JsonApiResource;

interface Encoder
{

    /**
     * @param Request|null $request
     * @return $this
     */
    public function withRequest($request): self;

    /**
     * @param $includePaths
     * @return $this
     */
    public function withIncludePaths($includePaths): self;

    /**
     * @param $fieldSets
     * @return $this
     */
    public function withFieldSets($fieldSets): self;

    /**
     * Create a compound document with a resource as the top-level data member.
     *
     * @param JsonApiResource|object|null $resource
     * @return JsonApiDocument
     */
    public function withResource(?object $resource): JsonApiDocument;

    /**
     * Create a compound document with a collection of resources as the top-level data member.
     *
     * @param iterable $resources
     * @return JsonApiDocument
     */
    public function withResources(iterable $resources): JsonApiDocument;

    /**
     * Create a document for a to-one relationship (identifier or null as the top-level data member.)
     *
     * @param JsonApiResource|object $resource
     * @param string $fieldName
     * @param JsonApiResource|object|null $related
     * @return JsonApiDocument
     */
    public function withToOne(object $resource, string $fieldName, ?object $related): JsonApiDocument;

    /**
     * Create a document for a to-many relationship (identifiers as the top-level data member.)
     *
     * @param JsonApiResource|object $resource
     * @param string $fieldName
     * @param iterable $related
     * @return JsonApiDocument
     */
    public function withToMany(object $resource, string $fieldName, iterable $related): JsonApiDocument;
}
