<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Encoder\Neomerx\Encoder;

use Neomerx\JsonApi\Encoder\Encoder as BaseEncoder;

class Encoder extends BaseEncoder
{

    /**
     * Serialize data to an array.
     *
     * @param $data
     * @return array
     */
    public function serializeData($data): array
    {
        return $this->encodeDataToArray($data);
    }

    /**
     * Serialize resource identifiers to an array.
     *
     * @param $data
     * @return array
     */
    public function serializeIdentifiers($data): array
    {
        return $this->encodeIdentifiersToArray($data);
    }

    /**
     * Serialize a meta document to an array.
     *
     * @param $meta
     * @return array
     */
    public function serializeMeta($meta): array
    {
        return $this->encodeMetaToArray($meta);
    }
}
