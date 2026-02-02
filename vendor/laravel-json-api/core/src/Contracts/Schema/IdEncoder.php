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

interface IdEncoder
{

    /**
     * Encode the model key to a JSON:API resource ID.
     *
     * @param string|int $modelKey
     * @return string
     */
    public function encode($modelKey): string;

    /**
     * Decode the JSON:API resource ID to a model key (id).
     *
     * Implementations must return `null` if the value cannot be
     * decoded to a model key.
     *
     * @param string $resourceId
     * @return string|int|null
     */
    public function decode(string $resourceId);
}
