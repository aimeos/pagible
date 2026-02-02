<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Contracts\Resources\Serializer;

use LaravelJsonApi\Contracts\Resources\JsonApiRelation;

interface Relation extends Hideable
{

    /**
     * Get the JSON:API field name for the serialized relation.
     *
     * @return string
     */
    public function serializedFieldName(): string;

    /**
     * Get the JSON representation of the relationship.
     *
     * @param object $model
     * @param string|null $baseUri
     * @return JsonApiRelation
     */
    public function serialize(object $model, ?string $baseUri): JsonApiRelation;
}
