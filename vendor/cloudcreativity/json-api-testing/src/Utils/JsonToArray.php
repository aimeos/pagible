<?php
/*
 * Copyright 2025 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace CloudCreativity\JsonApi\Testing\Utils;

use JsonException;
use RuntimeException;
use function is_array;
use function json_decode;
use function json_encode;

trait JsonToArray
{
    /**
     * @return array
     */
    public function toArray(): array
    {
        try {
            $value = json_decode(json_encode($this), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $ex) {
            throw new RuntimeException('Encountered an error converting JSON value to an array.', 0, $ex);
        }

        if (is_array($value)) {
            return $value;
        }

        throw new RuntimeException('JSON value does not decode as an array.');
    }
}
