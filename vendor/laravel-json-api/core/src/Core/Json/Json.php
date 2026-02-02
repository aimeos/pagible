<?php
/*
 * Copyright 2024 Cloud Creativity Limited
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Core\Json;

use JsonSerializable;
use LogicException;
use function is_array;

final class Json
{

    /**
     * Create a JSON array list (array with zero-indexed numeric keys).
     *
     * @param $value
     * @return Arr
     */
    public static function arr($value): Arr
    {
        if ($value instanceof Arr) {
            return $value;
        }

        if ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        if (is_array($value)) {
            return new Arr($value);
        }

        throw new LogicException('Unexpected JSON array list value.');
    }

    /**
     * Create a JSON hash (array with string keys).
     *
     * @param mixed $value
     * @return Hash
     */
    public static function hash($value): Hash
    {
        if ($value instanceof Hash) {
            return $value;
        }

        if ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        if (is_array($value) || is_null($value)) {
            return new Hash($value);
        }

        throw new LogicException('Unexpected JSON array hash value.');
    }
}
