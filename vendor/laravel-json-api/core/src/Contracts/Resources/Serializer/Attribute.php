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

interface Attribute extends Hideable
{

    /**
     * Get the JSON:API field name for the serialized attribute.
     *
     * @return string
     */
    public function serializedFieldName(): string;

    /**
     * Get the JSON value from the provided model.
     *
     * @param object $model
     * @return mixed
     */
    public function serialize(object $model);
}
